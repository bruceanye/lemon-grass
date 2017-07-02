<?php
/**
 * Created by PhpStorm.
 * Date: 13-11-23
 * Time: 下午9:10
 * @overview 用来进行回款相关的数据库操作
 * @author Meatill <lujia.zhai@dianjoy.com>
 * @since 3.1 (2013-11-23)
 */

namespace diy\service;

use dianjoy\BetterDate;
use diy\model\ADModel;
use diy\model\ChannelModel;
use diy\model\InvoiceModel;
use diy\utils\Utils;
use PDO;

class Payment extends Base {
  static $INIT_EMAIL_ADDRESSES = [
    'op@dianjoy.com',
    'JS@dianjoy.com',
    'costing@dianjoy.com',
    'jiewen.mo@dianjoy.com'
  ];

  const NO_PAYMENT = 0;
  const DELAY_DAYS = 10;
  const DELAY_MONTHS = 2;
  const CHECK = 1;
  const NO_CHECK = 0;

  /**
   * 取某段时期内所有回款记录
   * @param array $ad_ids 广告id
   * @param string $start 开始日期
   * @param string $end 结束日期
   * @return array
   */
  public function get_payment($ad_ids, $start, $end) {
    $DB = $this->get_read_pdo();
    $start = date(BetterDate::FORMAT, strtotime($start));
    $end = date(BetterDate::FORMAT, strtotime($end));
    list($conditions, $params) = $this->parse_filter(['id' => $ad_ids], ['is_append' => true]);
    $sql = "SELECT `id`,`month`,`payment`,`invoice`, `rmb`, `paid_time`,
              `invoice_time`, `invoice_rmb`, `payment_person`, `real_rmb`, `comment`
            FROM `t_ad_payment`
            WHERE `month`>=:start AND `month`<=:end $conditions";
    $state = $DB->prepare($sql);
    $state->execute(array_merge([':start' => $start, ':end' => $end], $params));
    return $state->fetchAll(PDO::FETCH_ASSOC);
  }

  public function get_payment_by_owner($start, $end, $owner = null) {
    $DB = $this->get_read_pdo();

    $sql = "SELECT a.`id`,`auto_id`,`month`,`payment`,`invoice`, `rmb`,`paid_time`,
            `invoice_time`, `invoice_rmb`, `payment_person`, `real_rmb`, `comment`
            FROM `t_ad_payment` AS a
              JOIN `t_ad_source` AS b ON a.id=b.id
            WHERE `month`>=:start AND `month`<=:end AND b.owner=:owner";
    $state = $DB->prepare($sql);
    $state->execute([
      ':start' => $start,
      ':end' => $end,
      ':owner' => $owner,
    ]);
    return $state->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_GROUP);
  }

  public function get_all_payments($start, $end) {
    $sql = "SELECT id,payment,rmb,paid_time,payment_person,invoice_time,invoice_rmb,real_rmb,comment,`month`
            FROM t_ad_payment
            WHERE `month`>=:start AND `month`<=:end";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $state->execute(array(':start' => $start, ':end' => $end));
    return $state->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_GROUP);
  }

  public function get_payment_stat($filters, $start, $end) {
    $start = $start . '-01';
    $end = date('Y-m-d', mktime(0, 0, 0, (int)substr($end, 5, 2) + 1, 0, substr($end, 0, 4)));
    $filters = array_merge($filters, array(
      'start_month' => $start,
      'end_month' => $end
    ));

    $payments = $this->get_ad_payments($filters);

    $cut_service = new ADCut();
    $cut_stat = $cut_service->get_cut_by_month($start, $end);

    $quote_service = new QuoteStat();
    $quote_stat = $quote_service->get_ad_income_by_month($start, $end);

    $invoice_service = new Invoice();
    $ads = $total = array();
    foreach ($payments as $id => $ad) {
      $ad_payment = array_merge(array('id' => $id),
        Utils::array_pick($ad[0], array('channel', 'ad_name', 'ad_app_type', 'create_time', 'cid', 'owner')));
      foreach ($ad as $payment) {
        $ad_payment['payment'][$payment['month']] = Utils::array_pick($payment, array('auto_id', 'month', 'payment', 'invoice', 'rmb', 'paid_time', 'invoice_time', 'payment_person', 'real_rmb', 'comment', 'invoice_rmb', 'predict_repay_date'));
        $ad_payment['payment'][$payment['month']]['income'] = $quote_stat[$id][substr($payment['month'], 0, 7)];
        $ad_payment['payment'][$payment['month']]['cut'] = $cut_stat[$id][substr($payment['month'], 0, 7)];
        $ad_payment['income'] += $quote_stat[$id][substr($payment['month'], 0, 7)];
        $ad_payment['cut'] += $cut_stat[$id][substr($payment['month'], 0, 7)];
        $ad_payment['rmb'] += $payment['rmb'];

        $invoice = $invoice_service->get_invoice_info_by_adid($id, $start, $end);
        $ad_payment['invoice_status'] = InvoiceModel::get_invoice_desc($invoice);
      }
      $ad_payment['payment'] = array_values($ad_payment['payment']);
      $ads[] = $ad_payment;
    }

    return $ads;
  }

  public function get_ad_payments($filters, $order = null, $method = null) {
    $DB = $this->get_read_pdo();

    list($conditions, $params) = $this->parse_filter($filters);
    if ($order) {
      $order = 'ORDER BY ' . $this->get_order($order);
    }
    $method = $method ? $method : (PDO::FETCH_ASSOC|PDO::FETCH_GROUP);

    $sql = "SELECT d.`id`,d.`id` AS `ad_id`,`auto_id`,`month`,`payment`,`invoice`,d.`rmb`,`paid_time`,`invoice_time`,`invoice_rmb`,
              `payment_person`,`real_rmb`,d.`comment`,ifnull(company_short,ifnull(alias,channel)) as `channel`,
              a.`ad_name`,`ad_app_type`,`create_time`,`cid`,b.`owner`,`predict_repay_date`
            FROM `t_ad_payment` AS d
              JOIN `t_adinfo` AS a ON d.`id`=a.`id`
              JOIN `t_ad_source` AS b ON a.`id`=b.`id`
              LEFT JOIN `t_channel_map` AS c ON b.`channel`=c.`id`
              LEFT JOIN `t_agreement` AS h ON b.`agreement_id`=h.`id`
            WHERE $conditions
            $order";
    $state = $DB->prepare($sql);
    $state->execute($params);
    return $state->fetchAll($method);
  }

  public function get_payments_depend_owner() {
    $DB = $this->get_read_pdo();

    $sql = "SELECT `owner`,`channel`,`send_date`
            FROM `t_ad_payment_mail`";
    return $DB->query($sql)->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_GROUP);
  }

  protected function parse_filter(array $filters = null, array $options = array()) {
    $defaults = ['to_string' => true];
    $options = array_merge($defaults, $options);

    if (isset($filters['ad_name'])) {
      $filters['a.ad_name'] = $filters['ad_name'];
      unset($filters['ad_name']);
    }

    if (isset($filters['start_month'])) {
      $filters['month'][] = array(
        'operator' => '>=',
        'data' => $filters['start_month']
      );
      unset($filters['start_month']);
    }

    if (isset($filters['end_month'])) {
      $filters['month'][] = array(
        'operator' => '<=',
        'data' => $filters['end_month']
      );
      unset($filters['end_month']);
    }

    $spec = array('keyword', 'salesman', 'channel');
    $pick = Utils::array_pick($filters, $spec);
    $filters = Utils::array_omit($filters, $spec);
    list($conditions, $params) = parent::parse_filter($filters, ['to_string' => false]);
    foreach ($pick as $key => $value) {
      switch ($key) {
        case 'keyword':
          if ($value) {
            $conditions[] = "(a.`ad_name` LIKE :keyword OR `channel` LIKE :keyword)";
            $params[':keyword'] = '%' . $value . '%';
          }
          break;

        case 'salesman':
          if ($value) {
            $conditions[] = "(b.`owner`=:salesman OR `execute_owner`=:salesman)";
            $params[':salesman'] = $value;
          }
          break;

        case 'channel':
          if ($value) {
            $conditions[] = " ifnull(company_short,ifnull(alias,channel))=:channel";
            $params[':channel'] = $value;
          }
          break;
      }
    }
    $conditions = $options['to_string'] ? ($options['is_append'] ? ' and ' : '') . implode(' AND ', $conditions) : $options;
    return array($conditions, $params);
  }
} 