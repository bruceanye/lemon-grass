<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 15/3/24
 * Time: 下午2:43
 */

namespace diy\service;


use diy\utils\Utils;
use PDO;

class AndroidStat extends Base {
  const HOUR = 'HOUR';
  const DATE = 'DATE';
  const TIME_FIELD = 'click_date';

  public function get_ad_click( array $filters, $group = '') {
    if (isset($filters['start'])) {
      $filters['click_date'][] = array(
        'operator' => '>=',
        'data' => $filters['start'],
      );
      unset($filters['start']);
    }
    if (isset($filters['end'])) {
      $filters['click_date'][] = array(
        'operator' => '<=',
        'data' => $filters['end'],
      );
      unset($filters['end']);
    }
    $DB = $this->get_read_pdo();
    list($conditions, $params) = $this->parse_filter( $filters );
    $group_sql = $group ? "`$group`," : '';
    $sql = "SELECT $group_sql SUM(`click_total`) AS `num`
            FROM `s_offer_click_log_stat_ad`
            WHERE $conditions";
    if ($group) {
      $sql .= "\nGROUP BY `$group`";
    }
    $state = $DB->prepare($sql);
    $state->execute($params);
    return $state->fetchAll($group ? PDO::FETCH_KEY_PAIR : PDO::FETCH_COLUMN);
  }

  public function get_ad_transfer( array $filters ) {
    if (isset($filters['start'])) {
      $filters['date'][] = array(
        'operator' => '>=',
        'data' => $filters['start'],
      );
      unset($filters['start']);
    }
    if (isset($filters['end'])) {
      $filters['date'][] = array(
        'operator' => '<=',
        'data' => $filters['end'],
      );
      unset($filters['end']);
    }
    $DB = $this->get_stat_pdo();
    list($conditions, $params) = $this->parse_filter( $filters);
    $sql = "SELECT `hour`, SUM(`number`) AS `total`
            FROM `s_android_notify_stat_ad`
            WHERE $conditions
            GROUP BY `hour`";
    $state = $DB->prepare($sql);
    $state->execute($params);
    return $state->fetchAll(PDO::FETCH_KEY_PAIR);
  }
}