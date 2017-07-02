<?php
/**
 * 通知类
 * User: meathill
 * Date: 13-12-19
 * Time: 上午10:59
 */

namespace diy\service;

use Mustache_Engine;
use PDO;
use SQLHelper;

class Notification extends Base {
  const LOG = 't_admin_alarm_log';

  static $NEW_AD = 20;
  static $EDIT_AD = 21;
  public static $REPLACE_AD = 28;
  static $EDIT_AD_COMMENT = 26;
  static $NEW_INVOICE = 30; // 新发票申请
  static $OP_INVOICE_PASS = 31; // 运营通过
  static $OP_INVOICE_FAIL = 32; // 运营拒绝
  static $SPECIAL_INVOICE_PASS = 33; // 特批通过
  static $SPECIAL_INVOICE_FAIL = 34; // 特批拒绝
  static $FINANCY_INVOICE_PASS = 35; // 财务通过
  static $FINANCY_INVOICE_FAIL = 36; // 财务拒绝
  static $MANAGER_INVOICE_PASS = 37; // 总监通过
  static $MANAGER_INVOICE_FAIL = 38; // 总监拒绝
  static $INVOICE_OPEN = 39; // 开票
  static $INVOICE_POST = 40; // 邮寄
  static $INVOICE_AD_CUT = 41; // 广告核减申请
  static $INVOICE_AD_CUT_REPLY = 42; // 广告核减申请已查阅
  static $INVOICE_AD_CUT_MANAGER_PASS = 43; // 发票申请-区域总监核减批复通过
  static $INVOICE_AD_CUT_MANAGER_FAIL = 44; // 发票申请-区域总监核减批复不通过

  static $NORMAL = 0;
  static $HANDLED = 1;

  public function send($attr) {
    $DB = $this->get_write_pdo();
    $sql = SQLHelper::create_insert_sql(self::LOG, $attr);
    $params = SQLHelper::get_parameters($attr);
    $state = $DB->prepare($sql);
    return $state->execute($params);
  }

  public function get_notice($admin_id, $role, $latest) {
    $DB = $this->get_read_pdo();
    $m = new Mustache_Engine();
    $ad_service = new AD();

    $sql = "SELECT `type`
            FROM `t_alarm_group`
            WHERE `group`=:role";
    $state = $DB->prepare($sql);
    $state->execute([':role' => $role]);
    $types = $state->fetchAll(PDO::FETCH_COLUMN);
    $types = implode(',', $types);
    $type_sql = $types ? " OR `alarm_type` IN ($types)" : '';

    // 只取最近一周，再早的估计也没啥处理的必要了
    $date = date('Y-m-d', time() - 86400 * 6);
    $sql = "SELECT a.`id`, `uid`, `user_id`, `app_id`, `ad_id`, a.`status`,
              `create_time`, `op_time`, `description`, `handler`, a.`alarm_type`
            FROM `t_admin_alarm_log` a
              LEFT JOIN `t_alarm_type` t ON a.alarm_type=t.id
            WHERE (`admin_id`='$admin_id' $type_sql)
              AND `create_time`>:date AND a.`status`=0 AND a.`id`>:latest
            ORDER BY `id` DESC";
    $state = $DB->prepare($sql);
    $state->execute([':date' => $date, ':latest' => $latest]);
    $alarms = $state->fetchAll(PDO::FETCH_ASSOC);
    $result = array();
    $alarm_types = array();
    foreach ($alarms as &$alarm) {
      $alarm['id'] = (int)$alarm['id'];
      if ($alarm['ad_id']) {
        if (strlen($alarm['ad_id']) == 32) {
          $ad = $ad_service->get_ad_info(array('id' => $alarm['ad_id']), 0, 1);
          $alarm['name'] = $ad['ad_name'];
        } elseif ($alarm['alarm_type'] == 19) {
          $agreement_service = new Agreement();
          $agreement = $agreement_service->get_agreement_info(array( 'id' => $alarm['ad_id']));
          $alarm['company'] = $agreement['company'];
          $alarm['ad_id'] = $agreement['agreement_id'];
        }
        $alarm['status'] = (int)$alarm['status'];
        $alarm['handler'] = $m->render($alarm['handler'], $alarm);
        array_push($result, $alarm);
      } else if ($alarm['uid'] && !in_array($alarm['alarm_type'], $alarm_types)) { // 发票通知提醒
        array_push($alarm_types, $alarm['alarm_type']);

        $invoice_id = $alarm['uid'];
        $invoice_service = new Invoice();
        $agreement_service = new Agreement();

        $invoice = $invoice_service->get_invoice_by_id($invoice_id);
        $agreement = $agreement_service->get_agreement_info(array( 'id' => $invoice['agreement_id']));
        $alarm['channel'] = $agreement['company_short'];

        $admin_service = new Admin();
        $sale = $admin_service->get_sales_info($alarm['user_id']);
        $alarm['sale'] = $sale['NAME'];

        $alarm['status'] = (int)$alarm['status'];
        $alarm['handler'] = $m->render($alarm['handler'], $alarm);
        array_push($result, $alarm);
      }
    }

    return $result;
  }

  public function get_notice_by_uid($uid) {
    $DB = $this->get_read_pdo();
    $sql = "SELECT `id`
            FROM `t_admin_alarm_log`
            WHERE `uid`=:uid";
    $state = $DB->prepare($sql);
    $state->execute(array(':uid' => $uid));
    return $state->fetchColumn();
  }

  public function set_status( array $filters, $HANDLED ) {
    $DB = $this->get_write_pdo();
    $now = date('Y-m-d H:i:s');
    list($conditions, $params) = $this->parse_filter( $filters );
    $sql = "UPDATE `t_admin_alarm_log`
            SET `status`=$HANDLED, `op_time`='$now'
            WHERE $conditions";
    $state = $DB->prepare($sql);
    return $state->execute($params);
  }

  public function get_notice_by_param($uid, $admin_id, $is_append = false, $alarm_type = null) {
    $DB = $this->get_read_pdo();

    $append_sql = $is_append ? ' AND `alarm_type`=' . $alarm_type . '' : '';
    $sql = "SELECT `id`
            FROM `t_admin_alarm_log`
            WHERE `uid`=:uid AND `admin_id`=:admin_id $append_sql";
    $state = $DB->prepare($sql);
    $state->execute(array(
      ':uid' => $uid,
      ':admin_id' => $admin_id
    ));
    return $state->fetchColumn();
  }

  public function update_notice_to_invoice($invoice_id, $applicant, $operation_id) {
    $DB_write = $this->get_write_pdo();

    $sql = "UPDATE `t_admin_alarm_log`
            SET `status`=1
            WHERE `uid`=:invoice_id AND `user_id`=:applicant AND `admin_id`!=:operation_id AND `status`=0";
    $state = $DB_write->prepare($sql);
    $state->execute(array(
      ':invoice_id' => $invoice_id,
      ':applicant' => $applicant,
      ':operation_id' => $operation_id
    ));
    return $state->fetchColumn();
  }
}