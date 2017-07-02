<?php
/**
 * Created by PhpStorm.
 * User: chensheng
 * Date: 15/11/13
 * Time: 下午6:03
 */

namespace diy\service;

use diy\model\ADCutModel;
use PDO;

class ADCut extends Base {
  public function get_cut_rmb($start, $end) {
    $DB = $this->get_read_pdo();
    $sql = "SELECT `ad_id`,sum(`rmb`)
            FROM `t_ad_cut`
            WHERE start<=:end AND end>=:start AND `status`=" . ADCutModel::STATUS_NORMAL .
            " GROUP BY `ad_id`";
    $state = $DB->prepare($sql);
    $state->execute(array(
      ':start' => $start,
      ':end' => $end
    ));
    return $state->fetchAll(PDO::FETCH_KEY_PAIR);
  }

  public function get_all_cut_rmb($start, $end) {
    $DB = $this->get_read_pdo();
    $sql = "SELECT sum(`rmb`)
            FROM `t_ad_cut`
            WHERE start<=:end AND end>=:start AND `status`=" . ADCutModel::STATUS_NORMAL;
    $state = $DB->prepare($sql);
    $state->execute(array(
      ':start' => $start,
      ':end' => $end
    ));
    return $state->fetch(PDO::FETCH_COLUMN);
  }

  public function get_cuts_info($filters) {
    list($conditions, $params) = $this->parse_filter($filters);
    $DB = $this->get_read_pdo();

    $sql = "SELECT *
            FROM `t_ad_cut`
            WHERE $conditions";
    $state = $DB->prepare($sql);
    $state->execute($params);
    return $state->fetchAll(PDO::FETCH_ASSOC);
  }

  public function get_cut_info($filters) {
    list($conditions, $params) = $this->parse_filter($filters);
    $DB = $this->get_read_pdo();

    $sql = "SELECT *
            FROM `t_ad_cut`
            WHERE $conditions";
    $state = $DB->prepare($sql);
    $state->execute($params);
    return $state->fetch(PDO::FETCH_ASSOC);
  }

  public function get_cut_by_month($start, $end) {
    $DB = $this->get_read_pdo();
    $sql = "SELECT `ad_id`,`start`,`end`,`rmb`
            FROM `t_ad_cut`
            WHERE `start`>=:start AND `end`<=:end AND `status`=:status";
    $state = $DB->prepare($sql);
    $params = array(
      ':start' => $start,
      ':end' => $end,
      ':status' => ADCutModel::STATUS_NORMAL
    );
    $state->execute($params);
    $result = $state->fetchAll(PDO::FETCH_ASSOC);

    $cut = array();
    foreach ($result as $value) {
      $real_end = min($end, $value['end']);
      for ($date = $value['start']; $date <= $real_end; $date = date('Y-m-d', mktime(0, 0, 0, (int)substr($date, 5, 2) + 1, 1, substr($date, 0, 4)))) {
        $month_end = date('Y-m-d', mktime(0, 0, 0, (int)substr($date, 5, 2) + 1, 1, substr($date, 0, 4)));
        if ($month_end > $real_end) {
          $month_end = $real_end;
        }
        $ads = explode(',', $value['ad_id']);
        foreach ($ads as $id) {
          $cut[$id][substr($date, 0, 7)] += $value['rmb'] * (strtotime($month_end) - strtotime($date) + 86400) / (strtotime($value['end']) - strtotime($value['start']) + 86400) / count($ads);
        }
      }
    }
    return $cut;
  }
}