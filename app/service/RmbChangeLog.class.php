<?php
/**
 * Created by PhpStorm.
 * User: chensheng
 * Date: 15/9/18
 * Time: 下午4:35
 */

namespace diy\service;

use diy\utils\Utils;
use PDO;

class RmbChangeLog extends Base {
  public function get_all_ad_rmb_change($start, $end, array $ad_ids) {
    list($conditions, $params) = $this->parse_filter(['id' => $ad_ids]);
    $sql = "select a.`pack_name`,a.`ad_app_type`,a.`ad_sdk_type`,a.`cpc_cpa`,`origin`,`new`,`datetime`
            from `t_ad_rmb_change_log` a 
              LEFT JOIN `t_adinfo` b ON a.`pack_name`=b.`pack_name`
            where type='step_rmb' AND b.$conditions
            ORDER BY `datetime` ASC";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $state->execute( $params );
    $logs = $state->fetchAll(PDO::FETCH_ASSOC);
    $map = [];
    foreach ( $logs as $log ) {
      $key = $this->generate_rmb_change_key( $log );
      $value = array_key_exists($key, $map) ? $map[$key] : [
        'min' => 0,
        'max' => 0,
      ];
      if ($log['datetime'] < $start) { // 在选定日期之前的调整,只记录最后一次的new
        $value['min'] = $value['max'] = $log['new'];
      } elseif ($log['datetime'] > $end) { // 在选定日期之后,记录第一次修改的origin
        if ($value['min'] == 0 && $value['max'] == 0) {
          $value['min'] = $value['min'] == 0 || $value['min'] > $log['origin'] ? $log['origin'] : $value['min'];
          $value['max'] = $value['max'] == 0 || $value['max'] < $log['origin'] ? $log['origin'] : $value['max'];
        }
      } else {
        $min = min($log['origin'], $log['new']);
        $max = max($log['origin'], $log['new']);
        $value['min'] = $value['min'] == 0 || $value['min'] > $min ? $min : $value['min'];
        $value['max'] = $value['max'] == 0 || $value['max'] < $max ? $max : $value['max'];
      }
      $map[$key] = $value;
    }
    return $map;
  }

  /**
   * @param $ad
   *
   * @return string
   */
  public function generate_rmb_change_key( $ad) {
    return implode( '_', Utils::array_pick( $ad, [ 'pack_name', 'ad_app_type', 'ad_sdk_type', 'cpc_cpa' ] ) );
  }
}