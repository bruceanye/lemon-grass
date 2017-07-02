<?php
/**
 * Created by PhpStorm.
 * Date: 2014/11/23
 * Time: 23:13
 * @overview 
 * @author Meatill <lujia.zhai@dianjoy.com>
 * @since 
 */

namespace diy\service;

use diy\model\ADModel;
use diy\utils\Utils;
use PDO;

class Apply extends Base {
  static $TABLE = 't_diy_apply';
  const NORMAL = 0;
  const ACCEPTED = 1;
  const DECLINED = 2;
  const WITHDRAWN = 3;
  const EXPIRED = 4;

  public function get_list($userid, $keyword = '', $start = 0, $pagesize = 10) {
    $DB = $this->get_read_pdo();
    list($conditions, $params) = $this->parse_filter([
      'userid' => $userid,
      'keyword' => $keyword,
    ], ['is_append' => true]);
    $sql = "SELECT a.`id`, `adid`, `set_status`, `set_job_num`, `set_rmb`,
              `set_ad_url`, `set_quote_rmb`, a.`create_time`, `handler`,
              `handle_time`, `send_msg`, `reply_msg`, a.`status`, i.`ad_name`,
              `cid`,COALESCE(NULLIF(`company_short`,''),`alias`,`channel`) AS `channel`
            FROM `t_diy_apply` a 
              JOIN `t_adinfo` i ON a.`adid`=i.`id`
              JOIN `t_ad_source` c ON a.`adid`=c.`id`
              LEFT JOIN `t_agreement` d ON c.`agreement_id`=d.`id`
              LEFT JOIN `t_channel_map` e ON c.`channel`=e.`id`
            WHERE a.`status`<:status $conditions
            ORDER BY `create_time` DESC
            LIMIT $start, $pagesize";
    $params[':status'] = self::WITHDRAWN;
    $state = $DB->prepare($sql);
    $state->execute($params);
    $result = $state->fetchAll(PDO::FETCH_ASSOC);

    return $result;
  }

  public function get_list_by_id( $ad_ids ) {
    $ad_ids = is_array($ad_ids) ? $ad_ids : [$ad_ids];
    $placeholder = implode(',', array_fill(0, count($ad_ids), '?'));
    $DB = $this->get_read_pdo();
    $sql = "SELECT `id`, `adid`, `set_status`, `set_job_num`, `set_rmb`,
              `set_ad_url`, `set_quote_rmb`
            FROM `" . self::$TABLE . "`
            WHERE `status`=0 AND `adid` IN ($placeholder)";
    $state = $DB->prepare($sql);
    $state->execute($ad_ids);
    return $state->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_UNIQUE);
  }

  public function get_by_applyid( $apply_id ) {
    $DB = $this->get_read_pdo();
    $sql = "SELECT `adid`,`set_status`,`set_job_num`,`set_rmb`,`set_ad_url`,`set_quote_rmb`
            FROM `" . self::$TABLE . "`
            WHERE `status`=0 AND `id`=:apply_id";
    $state = $DB->prepare($sql);
    $state->execute(array(
      ':apply_id' => $apply_id
    ));
    return $state->fetch(PDO::FETCH_ASSOC);
  }

  public function get_total_number($userid, $keyword) {
    $DB = $this->get_read_pdo();
    list($conditions, $params) = $this->parse_filter([
      'userid' => $userid,
      'keyword' => $keyword,
    ], ['is_append' => true]);
    $sql = "SELECT COUNT('x')
            FROM " . self::$TABLE . " a LEFT JOIN `t_adinfo` i ON a.`adid`=i.`id`
              LEFT JOIN `t_ad_source` c ON a.`adid`=c.`id`
            WHERE a.`status`<:status $conditions";
    $params[':status'] = self::WITHDRAWN;
    $state = $DB->prepare($sql);
    $state->execute($params);
    return $state->fetchColumn();
  }

  public function update($attr, $id) {
    $DB = $this->get_write_pdo();
    $attr['handle_time'] = date('Y-m-d H:i:s');
    return \SQLHelper::update($DB, self::$TABLE, $attr, $id);
  }

  public function update_ad_url($ad_url, $adid) {
    $DB_write = $this->get_write_pdo();

    $sql = "UPDATE `" . self::$TABLE ."`
            SET `set_ad_url`=:ad_url
            WHERE `adid`=:adid AND `status`=0";
    $state = $DB_write->prepare($sql);
    $state->execute(array(
      ':adid' => $adid,
      ':ad_url' => $ad_url
    ));
    return $state->fetchColumn();
  }

  public function is_owner($id, $me) {
    $DB = $this->get_read_pdo();
    $sql = "SELECT 'X'
            FROM `" . self::$TABLE . "`
            WHERE `id`=:id AND `userid`=:me";
    $state = $DB->prepare($sql);
    $state->execute(array(
      ':id' => $id,
      ':me' => $me,
    ));
    return $state->fetchColumn();
  }

  public function is_available( $id ) {
    $DB = $this->get_read_pdo();
    $sql = "SELECT 'X'
            FROM `" . self::$TABLE . "`
            WHERE `id`=:id AND `status`=" . self::NORMAL;
    $state = $DB->prepare($sql);
    $state->execute(array(':id' => $id));
    return $state->fetchColumn();
  }

  public function is_available_same_attr($adid, $attr) {
    $attr = $attr ? "`$attr` IS NOT NULL" : "`set_rmb` IS NULL AND `set_job_num` IS NULL AND `set_status` IS NULL AND `set_ad_url` IS NULL";
    $DB = $this->get_read_pdo();
    $sql = "SELECT 'X'
            FROM `" . self::$TABLE . "`
            WHERE `adid`=:adid AND `status`=" . self::NORMAL . " AND $attr";
    $state = $DB->prepare($sql);
    $state->execute(array(':adid' => $adid));
    return $state->fetchColumn();
  }

  public function get_offline_apply( array $filters ) {
    $DB = $this->get_read_pdo();
    $filters['set_status'] = ADModel::OFFLINE;
    list($conditions, $params) = $this->parse_filter( $filters );
    $sql = "SELECT `adid`, `send_msg`
            FROM `t_diy_apply`
            WHERE $conditions";
    $state = $DB->prepare($sql);
    $state->execute($params);
    return $state->fetch(PDO::FETCH_KEY_PAIR);
  }

  public function remove_replace_apply( $ad_id ) {
    $DB = $this->get_write_pdo();
    $sql = "UPDATE `t_diy_apply`
            SET `status`=" . self::WITHDRAWN . "
            WHERE `adid`=:ad_id AND `set_status` IS NULL AND `set_job_num` IS NULL
              AND `set_rmb` IS NULL AND `set_ad_url` IS NULL AND `status`=" . self::NORMAL;
    $state = $DB->prepare($sql);
    return $state->execute(array(':ad_id' => $ad_id));
  }

  protected function parse_filter( array $filters = null, array $options = [] ) {
    $defaults = ['to_string' => true];
    $options = array_merge($defaults, $options);
    
    $spec = ['keyword'];
    $omit = Utils::array_pick($filters, $spec);
    $filters = Utils::array_omit($filters, $spec);
    list($conditions, $params) = parent::parse_filter($filters, ['to_string' => false]);

    foreach ( $omit as $key => $value ) {
      switch ($key) {
        case 'keyword':
          if ($value) {
            $conditions[] = '(i.`ad_name` LIKE :keyword OR `channel` LIKE :keyword)';
            $params[':keyword'] = '%' . $value . '%';
          }
        break;
      }
    }
    
    if ($options['to_string']) {
      $conditions = count($conditions) ? implode(' AND ', $conditions) : 1;
    }
    if (!is_array($conditions) && $conditions && $options['is_append']) {
      $conditions = ' AND ' . $conditions;
    }
    return [$conditions, $params];
  }
} 