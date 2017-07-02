<?php
/**
 * Created by PhpStorm.
 * User: chensheng
 * Date: 15/11/13
 * Time: 下午6:47
 */

namespace diy\service;

use diy\model\ActivityModel;
use PDO;

class Activity extends Base {
  public function get_activity_rmb($start, $end) {
    $DB = $this->get_read_pdo();
    $sql = "SELECT sum(`rmb`)
            FROM `t_ad_activity`
            WHERE date>=:start AND date<=:end AND `status`=" . ActivityModel::STATUS_NORMAL;
    $state = $DB->prepare($sql);
    $state->execute(array(
      ':start' => $start,
      ':end' => $end
    ));
    $outcome = $state->fetch(PDO::FETCH_COLUMN);

    $sql = "SELECT sum(`rmb`)
            FROM `t_ad_activity`
            WHERE date>=:start AND date<=:end AND `status`=" . ActivityModel::STATUS_NORMAL .
            " AND `type`=" . ActivityModel::TYPE_CUSTOMER;
    $state = $DB->prepare($sql);
    $state->execute(array(
      ':start' => $start,
      ":end" => $end
    ));
    $income = $state->fetch(PDO::FETCH_COLUMN);

    return array(
      'income' => $income / 100,
      'outcome' => $outcome / 100
    );
  }

  public function get_ads_activity_rmb($start, $end) {
    $sql = "SELECT `ad_id`,`rmb`,`type`
            FROM `t_ad_activity`
            WHERE date>=:start AND date<=:end AND `status`=" . ActivityModel::STATUS_NORMAL;
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $state->execute(array(
      ':start' => $start,
      ':end' => $end
    ));
    $activity = $state->fetchAll(PDO::FETCH_ASSOC);

    $result = array();
    foreach ($activity as $value) {
      $ads = explode(',', $value['ad_id']);
      foreach ($ads as $id) {
        $result[$id]['outcome'] += $value['rmb'] / count($ads) / 100;
        if ($value['type'] == ActivityModel::TYPE_CUSTOMER) {
          $result[$id]['income'] += $value['rmb'] / count($ads) / 100;
        }
      }
    }

    return $result;
  }
}