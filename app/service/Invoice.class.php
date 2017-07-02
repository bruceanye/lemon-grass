<?php
/**
 * Created by PhpStorm.
 * User: chensheng
 * Date: 15/7/16
 * Time: 上午10:35
 */

namespace diy\service;

use diy\model\ADCutModel;
use diy\model\ADModel;
use diy\model\InvoiceModel;
use diy\utils\Utils;
use PDO;

class Invoice extends Base {
  /**
   * 获取发票详情信息
   * @param $filters
   * @param int $page_start
   * @param int $pagesize
   * @param null $order
   * @return array
   */
  public function get_invoice_info($filters, $page_start = 0, $pagesize = 10, $order = null) {
    $DB = $this->get_read_pdo();

    if (!array_key_exists('status', $filters)) {
      $filters['a.status'][] = array(
        'operator' => '>',
        'data' => 1
      );
    } else {
      $filters['a.status'] = $filters['status'];
      unset($filters['status']);
    }
    list($conditions, $params) = $this->parse_filter( $filters );

    $order = $order ? 'ORDER BY ' . $this->get_order($order) : '';
    $limit = $pagesize ? "LIMIT $page_start, $pagesize" : '';

    $sql = "SELECT a.`id`,`applicant`,`apply_time`,`company`,`income`,`income_first`,
            `handle_time`,`number`,`express_number`,a.`status`,a.`comment`,`attachment`,
            c.`agreement_id` as `agreement_number`
            FROM `t_invoice` a
              LEFT JOIN `t_agreement` c ON a.`agreement_id` = c.`id`
            WHERE $conditions
            $order
            $limit";
    $state = $DB->prepare($sql);
    $state->execute($params);
    $result = $state->fetchAll(PDO::FETCH_ASSOC);

    $noReadComments = $this->getNoReadComments();

    $invoices = array_map(function($item) use ($noReadComments) {
      $comments = $this->get_invoice_comment($item['id']);
      $open = in_array((int)$item['status'], InvoiceModel::$FAIL_STATUS) ? $item['status'] - 1 : $item['status'];
      return array_merge($item, [
        'comments' => $comments,
        'open' => $open,
        'is_show'  => $item['attachment'] || $item['comment'],
        'status'   => (int)$item['status'],
        'is_active' => $item['income'] != $item['income_first'] || $item['attachment'] || $item['comment'],
        'is_read' => $noReadComments[$item['id']] ? false : true
      ]);
    }, $result);
    return $invoices;
  }

  public function get_invoice_comment($invoice_id) {
    $DB = $this->get_read_pdo();
    $sql = "SELECT `comment`,`NAME` AS `handler`,`create_time`,`type`
            FROM `t_invoice_comment` a
              LEFT JOIN `t_admin` b ON a.`handler`=b.`id`
            WHERE `invoice_id`=:invoice_id
            ORDER BY a.`id` DESC";
    $state = $DB->prepare($sql);
    $state->execute([':invoice_id' => $invoice_id]);
    return $state->fetchAll(PDO::FETCH_ASSOC);
  }

  public function getNoReadComments() {
    $DB = $this->get_read_pdo();
    list($conditions, $params) = $this->parse_filter(['status' => [2, 3, 12, 13]]);
    $sql = "SELECT `invoice_id`, COUNT('X')
            FROM `t_invoice_comment`
            WHERE $conditions
            GROUP BY `invoice_id`";
    $state = $DB->prepare($sql);
    $state->execute($params);
    return $state->fetchAll(PDO::FETCH_KEY_PAIR);
  }

  /**
   * 查找发票付款方收款方的信息
   * @param $filters
   * @return array
   */
  public function searchInvoiceInfo($filters) {
    $DB = $this->get_read_pdo();

    list($conditions, $params) = $this->parse_filter($filters);
    $paySql = "SELECT `pay_charger`,`pay_telephone`,`pay_address`,`type`,`content_type`
               FROM `t_invoice`
               WHERE $conditions
               ORDER BY `id` DESC";
    $state = $DB->prepare($paySql);
    $state->execute($params);
    $res = $state->fetchAll(PDO::FETCH_ASSOC);
    $payInfo = count($res) ? $res[0] : [];

    list($conditions, $params) = $this->parse_filter(['applicant' => $_SESSION['id']]);
    $acceptSql = "SELECT `accept_address`,`accept_telephone`
                  FROM `t_invoice`
                  WHERE $conditions
                  ORDER BY `id` DESC";
    $state = $DB->prepare($acceptSql);
    $state->execute($params);
    $res = $state->fetchAll(PDO::FETCH_ASSOC);
    $acceptInfo = count($res) ? $res[0] : [];
    return array_merge($payInfo, $acceptInfo);
  }

  /**
   * 根据id查询发票信息
   * @param $id
   * @return array
   */
  public function get_invoice_by_id($id) {
    $DB = $this->get_read_pdo();

    $sql = "SELECT a.`id`,a.`applicant` AS `applicant_id`,`agreement_id`,`header`,`apply_time`, a.`type`, `content_type`,
            `income`,`income_first`,`joy_income`,`ad_income`,`ios_income`,`pay_charger`,`pay_telephone`,
            `pay_address`,`number`, `handle_time`,a.`status`,`sub_status`,`charger`,`accept_telephone`,`accept_address`,
            `attachment`, `comment`,`attachment_desc`,`start`,`end`,NAME AS `applicant`,`reason`,`kind`
            FROM `t_invoice` a
            JOIN `t_admin` c ON a.applicant = c.id
            WHERE a.`id`=:id";
    $state = $DB->prepare($sql);
    $state->execute(array(
      ':id' => $id
    ));
    return $state->fetch(PDO::FETCH_ASSOC);
  }

  /**
   * 返回发票表的最大id值
   * @return mixed
   */
  public function get_max_id() {
    $DB = $this->get_read_pdo();

    $sql = "SELECT max(`id`) FROM `t_invoice`";
    return $DB->query($sql)->fetch(PDO::FETCH_COLUMN);
  }

  /**
   * 获取发票数量
   * @param $filters
   * @return string
   */
  public function get_invoice_info_number($filters) {
    $DB = $this->get_read_pdo();
    if (!array_key_exists('status', $filters)){
      $filters['a.status'][] = array(
        'operator' => '>',
        'data' => 1
      );
    } else {
      $filters['a.status'] = $filters['status'];
      unset($filters['status']);
    }
    list($conditions, $params) = $this->parse_filter( $filters );

    $sql = "SELECT COUNT('X')
            FROM `t_invoice` a
            LEFT JOIN `t_agreement` c ON a.`agreement_id` = c.`id`
            WHERE $conditions";
    $state = $DB->prepare($sql);
    $state->execute($params);
    return $state->fetch(PDO::FETCH_COLUMN);
  }

  public function get_invoice_adids_by_invoiceid($invoice_id) {
    $DB = $this->get_read_pdo();
    $sql = "SELECT `ad_id`
            FROM `t_invoice_ad`
            WHERE `i_id`=:invoice_id";
    $state = $DB->prepare($sql);
    $state->execute(array(
      ':invoice_id' => $invoice_id
    ));
    return $state->fetchAll(PDO::FETCH_COLUMN);
  }

  public function get_invoice_ad_by_id($id) {
    $DB = $this->get_read_pdo();

    $sql = "SELECT *
            FROM `t_invoice_ad`
            WHERE `id`=:id";
    $state = $DB->prepare($sql);
    $state->execute(array(
      ':id' => $id
    ));
    return $state->fetch(PDO::FETCH_ASSOC);
  }

  public function get_invoice_ad_by_invoiceid($invoice_id) {
    $DB = $this->get_read_pdo();
    $sql = "SELECT a.`id`,a.`ad_id`,`quote_start_date`,`quote_end_date`,`ad_name`,
            `cid`,a.`quote_rmb`,`cpa`,`quote_rmb_after`,`cpa_after`,`remark`,a.`start`,
            a.`end`,a.`status`,d.`cut_type`,d.`id` AS `cut_id`,`reply_type`,`ad_app_type`
            FROM `t_invoice_ad` a
            LEFT JOIN `t_adinfo` b ON a.`ad_id` = b.`id`
            LEFT JOIN `t_ad_source` c ON a.`ad_id` = c.`id`
            LEFT JOIN `t_ad_cut` d ON (a.`i_id` = d.`invoice_id` AND a.`ad_id` = d.`ad_id`
              AND a.`quote_start_date` = d.`start` AND a.`quote_end_date` = d.`end`
              AND d.`status`!=". ADCutModel::STATUS_DEL . " AND d.`cut_type`!=" . ADCutModel::$CUT_TYPE_NONE . ")
            WHERE a.`i_id`=:id
              ORDER BY `reply_type` DESC";
    $state = $DB->prepare($sql);
    $state->execute(array(
      ':id' => $invoice_id
    ));
    $result = array();
    $invoice_ads = $state->fetchAll(PDO::FETCH_ASSOC);

    $android_income = 0; // android广告总收入
    $ios_income = 0; // ios广告总收入

    foreach ($invoice_ads as $value) {
      $income_first = $value['quote_rmb'] * $value['cpa'];
      $income_after = $value['quote_rmb_after'] * $value['cpa_after'];

      // 计算拆分的ios和android广告的总收入
      $ad_app_type = $value['ad_app_type'];
      if ($ad_app_type == ADModel::ANDROID) {
        $android_income += $income_after;
      } else {
        $ios_income += $income_after;
      }

      $money_cut = $income_first - $income_after;
      $rate = $income_first == 0 ? 0 : round((1- ($income_after / $income_first)) * 100, 2);
      $invoice_ad = array_merge($value, array(
        'income' => round($income_first / 100, 2),
        'income_after' => round($income_after / 100, 2),
        'money_cut' => $money_cut,
        'rate' => $rate,
        'quote_rmb' => round($value['quote_rmb'] / 100, 2),
        'quote_rmb_after' => round($value['quote_rmb_after'] / 100, 2)
      ));
      array_push($result, $invoice_ad);
    }
    return array($result, $android_income, $ios_income);
  }

  public function delete_invoice_ad_by_invoiceid_adid($invoice_id, $adids) {
    $DB = $this->get_write_pdo();

    $sql = "DELETE FROM `t_invoice_ad`
            WHERE `i_id` = :invoice_id AND `ad_id` IN ('$adids')";
    $state = $DB->prepare($sql);
    $state->execute(array(
      ':invoice_id' => $invoice_id,
    ));
    return $state->rowCount();
  }

  public function is_invoice($ad_id, $quote_start_date, $quote_end_date) {
    $DB = $this->get_read_pdo();

    $sql = "SELECT COUNT('X')
            FROM `t_invoice_ad` a
              LEFT JOIN `t_invoice` b ON a.`i_id`=b.`id`
            WHERE `ad_id`=:ad_id AND `quote_start_date`>=:quote_start_date AND `quote_end_date`<=:quote_end_date
              AND b.`status`!=" . InvoiceModel::$DEL_STATUS;
    $state = $DB->prepare($sql);
    $state->execute(array(
      ':ad_id' => $ad_id,
      ':quote_start_date' => $quote_start_date,
      ':quote_end_date' => $quote_end_date
    ));
    return $state->fetchColumn();
  }

  public function getAccountChecks($start, $end, $check) {
    $DB = $this->get_read_pdo();

    $sql = "SELECT `id`
            FROM `t_ad_payment`
            WHERE LEFT(`month`, 7)>=:start AND LEFT(`month`, 7)<=:end AND `account_check`=:check";
    $state = $DB->prepare($sql);
    $state->execute([':start' => substr($start, 0, 7), ':end' => substr($end, 0, 7), ':check' => $check]);
    $paymentChecks = $state->fetchAll(PDO::FETCH_COLUMN);

    $checkSql = "SELECT `ad_id`,`check_date`
                 FROM `t_ad_account_check`
                 WHERE `check_date`>=:start AND `check_date`<=:end AND `status`=:check";
    $state = $DB->prepare($checkSql);
    $state->execute([':start' => $start, ':end' => $end, ':check' => $check]);
    $accountChecks = $state->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_COLUMN);
    return array($paymentChecks, $accountChecks);
  }

  public function update_invoice_ad($params) {
    $DB = $this->get_write_pdo();

    $sql = "UPDATE `t_invoice_ad`
            SET `cpa`=:cpa,`quote_rmb`=:quote_rmb,
            `cpa_after`=:cpa,`quote_rmb_after`=:quote_rmb
            WHERE `ad_id`=:ad_id AND `quote_start_date`=:quote_start_date
            AND `quote_end_date`=:quote_end_date";
    $state = $DB->prepare($sql);
    $state->execute($params);
    return $state->fetchColumn();
  }

  public function get_invoice_info_by_id($id) {
    $DB = $this->get_read_pdo();
    $sql = "SELECT * FROM t_invoice
            WHERE `id`=:id";
    $state = $DB->prepare($sql);
    $state->execute(array(
      ':id' => $id
    ));
    return $state->fetch(PDO::FETCH_ASSOC);
  }

  public function get_invoice_info_by_adid($ad_id, $start, $end) {
    $DB = $this->get_read_pdo();
    $sql = "SELECT a.*
            FROM `t_invoice` a
              JOIN `t_invoice_ad` b ON a.`id`=b.`i_id`
            WHERE `ad_id`=:ad_id AND b.`start`>=:start AND b.`end`<=:end";
    $state = $DB->prepare($sql);
    $params = array(
      ':ad_id' => $ad_id,
      ':start' => $start,
      ':end' => $end
    );
    $state->execute($params);
    return $state->fetch(PDO::FETCH_ASSOC);
  }

  protected function parse_filter(array $filters = null, array $options = array()) {
    $defaults = ['to_string' => true];
    $options = array_merge($defaults, $options);

    if (isset($filters['salesman'])) {
      $filters['applicant'][] = array(
        'operator' => 'in',
        'data' => $filters['salesman']
      );
      unset($filters['salesman']);
    }
    if (isset($filters['start'])) {
      $filters['apply_time'][] = array(
        'operator' => '>=',
        'data' => $filters['start']
      );
      unset($filters['start']);
    }
    if (isset($filters['end'])) {
      $filters['apply_time'][] = array(
        'operator' => '<=',
        'data' => $filters['end'],
      );
      unset($filters['end']);
    }
    if (isset($filters['pass_status'])) {
      $filters['a.status'][] = array(
        'operator' => '>=',
        'data' => $filters['pass_status'],
      );
      unset($filters['pass_status']);
    }

    $spec = array('keyword');
    $pick = Utils::array_pick($filters, $spec);
    $filters = Utils::array_omit($filters, $spec);
    list($conditions, $params) = parent::parse_filter( $filters, array('to_string' => false) );
    foreach ($pick as $key => $value) {
      switch ($key) {
        case 'keyword':
          if ($value) {
            $conditions[] = "(`express_number` LIKE :keyword OR `company` LIKE :keyword)";
            $params[':keyword'] = "%$value%";
          }
          break;
      }
    }
    $conditions = $options['to_string'] ? ($options['is_append'] ? ' and ' : '') . implode(' AND ', $conditions) : $options;
    return array($conditions, $params);
  }
}