<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 15/5/19
 * Time: 下午3:25
 */

namespace diy\service;


use diy\model\ADModel;
use PDO;
use diy\utils\Utils;

class Agreement extends Base {
  static $TYPE = ['无', 'CP', '网盟', '换量', '个人', '开发者', '外放渠道', '其他'];

  public function get_agreements( $filters = null, $method = null ) {
    $DB = $this->get_read_pdo();
    if (array_key_exists('id', $filters)) {
      $filters['a.id'] = $filters['id'];
      unset($filters['id']);
    }
    list($conditions, $params) = $this->parse_filter( $filters );
    $method = $method ? $method : PDO::FETCH_ASSOC | PDO::FETCH_UNIQUE;

    $sql = "SELECT a.*,c.`status`,`status_time`,`is_vip`,e.`NAME` AS `owner_name`,
              COALESCE(NULLIF(`company`,''), `full_name`) AS `company`,
              COALESCE(NULLIF(`company_short`,''), `alias`) AS `company_short`
            FROM `t_agreement` a 
              LEFT JOIN `t_ad_source` b ON a.`id`=b.`agreement_id`
              LEFT JOIN `t_adinfo` c ON c.`id`=b.`id`
              LEFT JOIN `t_channel_map` d ON a.`channel_id`=d.`id`
              LEFT JOIN `t_admin` e ON a.`owner`=e.`id`
            WHERE $conditions
            ORDER BY a.`id` DESC";
    $state = $DB->prepare($sql);
    $state->execute($params);
    $result = $state->fetchAll($method);
    if ($method & PDO::FETCH_GROUP && !($method & 131072)) {
      foreach ( $result as $key => $group ) { // 选出在线或最新的广告
        usort($group, function ($a, $b) {
          // 删掉的广告，状态和状态时间认为无效
          if ($a['status'] < 0 || $a['status'] > 1) {
            $a['status'] = 2;
            $a['status_time'] = '';
          }
          if ($b['status'] < 0 || $b['status'] > 1) {
            $b['status'] = 2;
            $b['status_time'] = '';
          }
          $status = (int)$a['status'] - (int)$b['status'];
          if ($status != 0) {
            return $status;
          }
          $date_a = max($a['doc_date'], $a['status_time']);
          $date_b = max($b['doc_date'], $b['status_time']);
          return strtotime($date_b) - strtotime($date_a);
        });
        $group[0]['id'] = $key;
        $result[$key] = $group[0];
      }
    }

    foreach ( $result as &$agreement ) {
      $agreement['company_type'] = (int)$agreement['company_type'];
      $agreement['is_vip'] = (int)$agreement['is_vip'];
      if ($agreement['status'] !== ADModel::ONLINE) {
        if ($agreement['status'] != ADModel::OFFLINE) {
          $agreement['status_time'] = '';
        }
        $date = max($agreement['doc_date'], $agreement['status_time']);
        $agreement['need_renew'] = (time() - strtotime($date)) / 86400 >= 50;
      }
    }
    return $result;
  }

  public function get_agreements_basic( $filters = null ) {
    $DB = $this->get_read_pdo();
    list($conditions, $params) = $this->parse_filter($filters);
    $sql = "SELECT a.`id`, `company_type`,
              COALESCE(NULLIF(`company`,''), `full_name`) AS `company`,
              COALESCE(NULLIF(`company_short`,''), `alias`) AS `company_short`
            FROM `t_agreement` a
              LEFT JOIN t_channel_map b ON a.`channel_id`=b.`id`
            WHERE $conditions";
    $state = $DB->prepare($sql);
    $state->execute($params);
    return $state->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_UNIQUE);
  }

  public function get_agreement_by_adid($ad_id) {
    $DB = $this->get_read_pdo();
    $sql = "SELECT c.`id`,`company`
            FROM `t_adinfo` a
            LEFT JOIN `t_ad_source` b ON a.`id`=b.`id`
            LEFT JOIN `t_agreement` c ON b.`agreement_id`=c.`id`
            WHERE a.`id`=:ad_id";
    $state = $DB->prepare($sql);
    $state->execute(array(
      ':ad_id' => $ad_id
    ));
    return $state->fetch(PDO::FETCH_ASSOC);
  }

  public function get_my_agreement($filters = null, $page_start = 0, $page_size = 20) {
    // 取当前用户的助理和被助理
    $sales = $this->get_assistant();
    $filters['sales'] = $sales;

    $result = $this->get_agreements($filters, PDO::FETCH_ASSOC | PDO::FETCH_GROUP);
    $result = array_slice($result, $page_start, $page_size);

    return $result;
  }


  public function get_my_agreement_total(array $filters = []) {
    $DB = $this->get_read_pdo();

    // 取当前用户的助理和被助理
    $sales = $this->get_assistant();
    $filters['owner'] = $sales;
    list($conditions, $params) = $this->parse_filter( $filters );

    $sql = "SELECT COUNT('X')
            FROM `t_agreement`
            WHERE $conditions";
    $state = $DB->prepare($sql);
    $state->execute($params);
    return $state->fetch(PDO::FETCH_COLUMN);
  }

  /**
   * 返回渠道信息，暂时只有报备邮件那里用，所以按照以前返回简称即可
   * @param array $filters
   *
   * @return string
   */
  public function get_channel( array $filters ) {
    $DB = $this->get_read_pdo();
    list($conditions, $params) = $this->parse_filter( $filters );
    $sql = "SELECT `id`, `alias`
            FROM `t_channel_map`
            WHERE $conditions";
    $state = $DB->prepare($sql);
    $state->execute($params);
    return $state->fetchAll(PDO::FETCH_KEY_PAIR);
  }

  /**
   * 取当前用户的助理和被助理
   * @return array|null|string
   */
  public function get_assistant() {
    $me = $_SESSION['id'];
    $admin = new Admin();

    $sales = $admin->get_sales_by_me($me);
    $owner = $admin->get_owner(null, $me);
    if ($sales) {
      $sales = array_keys($sales);
    }
    if ($owner) {
      $sales = array_unique(array_merge(array_values($owner), (array)$sales));
    }
    return array_values($sales);
  }

  public function get_agreement_info( array $filters ) {
    $DB = $this->get_read_pdo();
    list($conditions, $params) = $this->parse_filter($filters);

    $sql = "SELECT *
            FROM `t_agreement`
            WHERE $conditions";
    $state = $DB->prepare($sql);
    $state->execute($params);
    return $state->fetch(PDO::FETCH_ASSOC);
  }

  /**
   * @param array $filters
   * @param array $options
   *
   * @return array
   */
  protected function parse_filter( array $filters = null, array $options = array() ) {
    $defaults = ['to_string' => true];
    $options = array_merge($defaults, $options);
    $spec = array('keyword', 'today', 'sales');
    $pick = Utils::array_pick($filters, $spec);
    $filters = Utils::array_omit($filters, $spec);
    list($conditions, $params) = parent::parse_filter( $filters, array('to_string' => false));
    if ($pick) {
      foreach ($pick as $key => $value) {
        switch ($key) {
          case 'keyword':
            if ($value) {
              $conditions[] = "(`company` LIKE :keyword OR `company_short` LIKE :keyword OR a.`agreement_id` LIKE :keyword OR `full_name` LIKE :keyword OR `alias` LIKE :keyword)";
              $params[':keyword'] = '%' . $value . '%';
            }
            break;

          case 'today':
            $value = date('Y-m-d', strtotime($value) - 86400 * 60);
            $conditions[] = '(`doc_date`>=:protection_begin OR c.`status`=' . ADModel::ONLINE .'
            OR (c.`status`=' . ADModel::OFFLINE . ' AND `status_time`>=:protection_begin))';
            $params[':protection_begin'] = $value;
            break;

          case 'sales':
            if ($value) {
              $value = is_array($value) ? $value : [$value];
              $salesConditions = [];
              foreach ( ['a.owner', 'android_sales', 'ios_sales'] as $key ) {
                $filter = [
                  'operator' => 'IN',
                  'data' => $value,
                ];
                list($condition, $salesParams) = $this->parse_filter_by_operator($key, $filter, 'owner');
                $salesConditions = array_merge($salesConditions, $condition);
                $params = array_merge($params, $salesParams);
              }
              $conditions[] = '(' . implode(' OR ', $salesConditions) . ')';
            }
            break;
        }
      }
    }
    if ($options['to_string']) {
      $conditions = count($conditions) ? implode(' AND ', $conditions) : 1;
    }
    if (!is_array($conditions) && $conditions && $options['is_append']) {
      $conditions = ' AND ' . $conditions;
    }
    return array($conditions, $params);
  }
}