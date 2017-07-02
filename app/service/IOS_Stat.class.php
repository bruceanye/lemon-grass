<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 15/3/23
 * Time: 下午1:29
 */

namespace diy\service;


use diy\utils\Utils;
use PDO;

class IOS_Stat extends Base {
  const HOUR = 'HOUR';
  const DATE = 'DATE';
  const TIME_FIELD = 'stat_date';

  public function get_ad_click( $filters, $group, $type = 'DATE' ) {
    $DB = $this->get_stat_pdo();
    if ($filters['end']) {
      $filters['end'] = date('Y-m-d', strtotime($filters['end']) + 86400);
    }
    list($conditions, $params) = $this->parse_filter( $filters );
    $group_field = $group ? "$type(`$group`)," : '';
    $sql = "SELECT $group_field SUM(`num`) AS `num`
            FROM `s_ios_click`
            WHERE $conditions";
    if ($group) {
      $sql .= "\nGROUP BY $type(`$group`)";
    }
    $state = $DB->prepare($sql);
    $state->execute($params);
    return $state->fetchAll(PDO::FETCH_KEY_PAIR);
  }

  public function get_ad_transfer( $filters, $group, $type = 'DATE') {
    $DB = $this->get_stat_pdo();
    list($conditions, $params) = $this->parse_filter( $filters );
    $group_field = $group ? "$type(`$group`)," : '';
    $sql = "SELECT $group_field `num`
            FROM `s_ios_transfer`
            WHERE $conditions";
    if ($group) {
      $sql .= "\nGROUP BY $type(`$group`)";
    }
    $state = $DB->prepare($sql);
    $state->execute($params);
    return $state->fetchAll(PDO::FETCH_KEY_PAIR);
  }

  protected function parse_filter(array $filters = null, array $options = array() ) {
    $defaults = ['to_string' => true];
    $options = array_merge($defaults, $options);

    if (isset($filters['start'])) {
      $filters['stat_date'][] = array(
        'operator' => '>=',
        'data' => $filters['start'],
      );
      unset($filters['start']);
    }
    if (isset($filters['end'])) {
      $filters['stat_date'][] = array(
        'operator' => '<=',
        'data' => $filters['end'],
      );
      unset($filters['end']);
    }
    $spec = array('date');
    $pick = Utils::array_pick($filters, $spec);
    $filters = Utils::array_omit($filters, $spec);
    list($conditions, $params) = parent::parse_filter( $filters, ['to_string' => false] );
    foreach ($pick as $key => $value) {
      switch ($key) {
        case 'date':
          $conditions[] = "DATE(`stat_date`)=:date";
          $params[':date'] = $value;
          break;
      }
    }
    $conditions = $options['to_string'] ? ($options['is_append'] ? ' and ' : '') . implode(' AND ', $conditions) : $options;
    return array($conditions, $params);
  }
}