<?php
/**
 * Date: 13-10-23
 * Time: 下午2:17
 * @overview log ad operations
 * @author Meathill <lujia.zhai@dianjoy.com>
 */
namespace diy\service;

use PDO;
use SQLHelper;

class ADOperationLogger extends Base {
  const TABLE = 't_ad_operation_log';
  const CP_TABLE = 't_ad_diy_operation_log';
  const SUCCESS = 0;
  const FAIL = 1;
  protected $logs;
  protected $user;

  public function __construct() {
    $this->logs = array();
    $this->user = $_SESSION['id'];
  }

  public function add($adid, $type, $action, $comment) {
    $this->logs[] = array(
      'adid' => $adid,
      'type' => $type,
      'user' => $this->user,
      'action' => $action,
      'comment' => $comment,
      'datetime' => date("Y-m-d H:i:s"),
    );
  }

  public function log($adid, $type, $action, $comment = '', $is_ok = 0) {
    $DB = $this->get_write_pdo();
    $now = date('Y-m-d H:i:s');
    $sql = $this->getTemplate() . "(:me, :adid, :type, :action, :comment, :is_ok, '$now')";
    $state = $DB->prepare($sql);
    return $state->execute(array(
      ':me' => $this->user,
      ':adid' => $adid,
      ':type' => $type,
      ':action' => $action,
      ':comment' => $comment,
      ':is_ok' => $is_ok,
    ));
  }

  public function logAll($is_ok = 0) {
    foreach ($this->logs as $key => $value) {
      $this->logs[$key]['is_ok'] = $is_ok;
    }

    $DB_write = $this->get_write_pdo();
    if( SQLHelper::insert_update_multi($DB_write, self::TABLE, $this->logs)) {
      $this->logs = array();
      return true;
    }
    return false;
  }

  public function get_list($id, $start, $end) {
    $DB = $this->get_read_pdo();
    $sql = "SELECT a.*,b.`NAME`
            FROM `t_ad_operation_log` AS a JOIN `t_admin` AS b ON a.`user`=b.`id`
            WHERE `adid`=:id AND !(`type`='quote' && `action`='insert')
              AND `is_ok`=0 AND `datetime`>=:start AND date(`datetime`)<=:end
            ORDER BY `datetime` DESC,`id` DESC";
    $state = $DB->prepare($sql);
    $state->execute(array(
      ':id' => $id,
      ':start' => $start,
      ':end' => $end,
    ));
    return $state->fetchAll(PDO::FETCH_ASSOC);
  }

  private function getTemplate() {
    $table = $_SESSION['role'] == Auth::$CP_PERMISSION ? self::CP_TABLE : self::TABLE;
    $sql = "INSERT INTO `$table`
            (`user`, `adid`, `type`, `action`, `comment`, `is_ok`, `datetime`)
            VALUES ";
    return $sql;
  }

  public function get_log( $filters ) {
    $DB = $this->get_read_pdo();
    list($conditions, $params) = $this->parse_filter( $filters );
    $sql = "SELECT l.*, a.`NAME`
            FROM `t_ad_operation_log` AS l JOIN `t_admin` AS a ON l.`user`=a.`id`
            WHERE $conditions
            ORDER BY l.`id` DESC
            LIMIT 1";
    $state = $DB->prepare($sql);
    $state->execute($params);
    return $state->fetch(PDO::FETCH_ASSOC);
  }
}