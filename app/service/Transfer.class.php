<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 15/2/26
 * Time: 下午5:30
 */

namespace diy\service;

use diy\model\ADModel;
use diy\utils\Utils;
use PDO;

class Transfer extends Base {
  /**
   * 取广告统计
   * @param array $filters
   * @param string $group
   *
   * @return array
   */
  public function get_ad_transfer( array $filters, $group = '' ) {
    $DB = $this->get_read_pdo();
    list($conditions, $params) = $this->parse_filter( $filters );
    $group_sql = $group ? "`$group`," : '';
    $sql = "SELECT $group_sql SUM(`transfer_total`) AS `transfer`
            FROM `s_transfer_stat_ad` a
              JOIN `t_adinfo` b ON a.`ad_id`=b.`id`
            WHERE $conditions";
    if ($group) {
      $sql .= " \nGROUP BY `$group`";
    }
    $state = $DB->prepare($sql);
    $state->execute($params);
    return $group ? $state->fetchAll(PDO::FETCH_KEY_PAIR) : $state->fetchColumn();
  }

  /**
   * 取统计详情
   *
   * @param array $filters
   *
   * @return array
   */
  public function get_ad_transfer_detail( array $filters ) {
    $DB = $this->get_read_pdo();
    list($conditions, $params) = $this->parse_filter( $filters );
    $sql = "SELECT `transfer_date`, `ad_id`, `transfer_total`
            FROM `s_transfer_stat_ad`
            WHERE $conditions";
    $state = $DB->prepare($sql);
    $state->execute($params);
    return $state->fetchAll(PDO::FETCH_ASSOC);
  }

  public function get_ad_transfer_by_pack_name( $pack_names, $start ) {
    $DB = $this->get_read_pdo();
    $keys = implode(',', array_fill(0, count($pack_names), '?'));
    $start = date('Y-m-d', 86400 * $start);
    $sql = "SELECT `pack_name`
            FROM `s_transfer_stat_ad` AS a JOIN `t_adinfo` AS b ON a.`ad_id`=b.`id`
            WHERE `transfer_date`>='$start' AND `pack_name` IN ($keys)
            GROUP BY `pack_name`";
    $state = $DB->prepare($sql);
    $state->execute($pack_names);
    return $state->fetchAll(PDO::FETCH_COLUMN);
  }

  public function get_transfer_stat($start, $end) {
    $DB = $this->get_read_pdo();

    $sql = "SELECT `ad_id`
            FROM `s_transfer_stat_ad`
            WHERE `transfer_date`>=:start AND `transfer_date`<=:end";
    $state = $DB->prepare($sql);
    $state->execute(array(
      ':start' => $start,
      ':end' => $end,
    ));
    return $state->fetchAll(PDO::FETCH_COLUMN);
  }

  public function get_approach_satiate(array $filters) {
    $filters['oversea'] = 0;
    $filters['ad_app_type'] = ADModel::ANDROID;
    $range = [
      ':start' => $filters['start'],
      ':end' => $filters['end'],
    ];
    $filters = Utils::array_omit($filters, 'start', 'end');
    list($conditions, $params) = $this->parse_filter($filters, ['is_append' => true]);
    $sql = "SELECT `pack_name`
            FROM
              (SELECT `pack_name`,HOUR(`transfer_time`) AS `hour`,COUNT('X') AS `count`
               FROM `t_offer_transfer_log` a
                  JOIN `t_adinfo` b ON a.`ad_id`=b.`id`
               WHERE `transfer_time`>=:start AND `transfer_time`<:end $conditions
               GROUP BY `pack_name`,HOUR(`transfer_time`) HAVING count>30) g
            GROUP BY `pack_name` HAVING COUNT('X')>14";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $state->execute(array_merge($range, $params));
    return $state->fetchAll(PDO::FETCH_COLUMN);
  }

  protected function parse_filter(array $filters = null, array $options = array() ) {
    if (isset($filters['start'])) {
      $filters['transfer_date'][] = array(
        'operator' => '>=',
        'data' => $filters['start'],
      );
      unset($filters['start']);
    }
    if (isset($filters['end'])) {
      $filters['transfer_date'][] = array(
        'operator' => '<=',
        'data' => $filters['end'],
      );
      unset($filters['end']);
    }
    if (isset($filters['date'])) {
      $filters['transfer_date'] = $filters['date'];
      unset($filters['date']);
    }
    return parent::parse_filter( $filters, $options );
  }
}