<?php
/**
 * Created by PhpStorm.
 * User: chensheng
 * Date: 15/8/24
 * Time: 上午11:59
 */

namespace diy\service;

use PDO;

class NativeStat extends Base {
  public function __construct() {
    $this->DB = $this->get_read_pdo();
  }

  public function get_native_stat_ad($start, $end) {
    $sql = "SELECT `ad_id`,sum(`nums`) as num
            FROM `s_native_stat_ad`
            WHERE `view_date`>=:start AND `view_date`<=:end
            GROUP BY `ad_id`";
    $state = $this->DB->prepare($sql);
    $state->execute([':start' => $start, ':end' => $end]);
    return $state->fetchAll(PDO::FETCH_KEY_PAIR);
  }

  public function get_native_transfer_stat_by_ad($start, $end) {
    $sql = "SELECT `ad_id`,sum(`transfer_total`)
            FROM `s_transfer_stat_app_ad` as a join `t_appinfo` as b
            ON a.`app_id` = b.`id`
            WHERE `transfer_date`>=:start AND `transfer_date`<=:end AND `delivery_type`=1
            GROUP BY `ad_id`";
    $state = $this->DB->prepare($sql);
    $state->execute([':start' => $start, ':end' => $end]);
    return $state->fetchAll(PDO::FETCH_KEY_PAIR);
  }
}