<?php
/**
 * Created by PhpStorm.
 * User: hemengyuan
 * Date: 15/5/11
 * Time: 下午3:06
 */

namespace diy\service;

use PDO;

class TaskStat extends Base {
  const TASK_ES_TYPE = 's_task_stat_task_app';
  const LIMITED_TASK_ES_TYPE = 's_limited_task_stat_ad_app';

  public function get_ad_task_stat_by_ad($start, $end, $filters = array()) {
    list($conditions, $params) = $this->parse_filter($filters, ['is_append' => true]);
    $sql = "SELECT `ad_id`,SUM(`num`) AS `num`,SUM(`rmb`) AS `rmb`
            FROM `s_task_stat` a JOIN `t_task` b ON a.task_id=b.id
            JOIN `t_adinfo` c ON b.`ad_id`=c.`id`
            WHERE `task_date`>=:start AND `task_date`<=:end {$conditions}
            GROUP BY `ad_id`";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $params = array_merge($params, array(
      ':start' => $start,
      ':end' => $end
    ));
    $state->execute($params);
    return $state->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_UNIQUE);
  }

  public function get_ad_limited_task_stat_by_ad($start, $end, $filters = null) {
    list($conditions, $params) = $this->parse_filter($filters, ['is_append' => true]);
    $sql = "SELECT `ad_id`,sum(`num`) AS `num`,sum(`rmb`) AS `rmb`
            FROM `s_limited_task_stat` AS a JOIN `t_adinfo` AS b ON a.ad_id=b.id
            WHERE `task_date`>=:start AND `task_date`<=:end $conditions
            GROUP BY `ad_id`";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $state->execute(array_merge($params, [ ':start' => $start, ':end' => $end ] ));
    return $state->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_UNIQUE);
  }

  public function get_ad_task_outcome_by_date($start, $end, $id) {
    $sql = "select a.task_date,sum(a.rmb) as outcome from s_task_stat as a join t_task as b
    on a.task_id=b.id where a.task_date>=:start and a.task_date<=:end and b.ad_id=:id group by a.task_date";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $state->execute(array(':start' => $start, ':end' => $end, ':id' => $id));
    return $state->fetchAll(PDO::FETCH_KEY_PAIR);
  }

  public function get_ad_limit_task_outcome_by_date($start, $end, $id) {
    $sql = "select task_date,sum(rmb) as outcome from s_limited_task_stat
    where task_date>=:start and task_date<=:end and ad_id=:id group by task_date";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $state->execute(array(':start' => $start, ':end' => $end, ':id' => $id));
    return $state->fetchAll(PDO::FETCH_KEY_PAIR);
  }

  public function get_ad_task_stat($start, $end) {
    $DB = $this->get_read_pdo();
    $sql = "SELECT `ad_id`,SUM(`num`) AS `num`,SUM(`rmb`) AS `rmb`,SUM(`ready`) AS `ready`
            FROM `s_task_stat` AS a
              JOIN `t_task` AS b ON a.`task_id`=b.`id`
            WHERE `task_date`>='$start' AND `task_date`<='$end'
              GROUP BY `ad_id`";
    return $DB->query($sql)->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_UNIQUE|PDO::FETCH_GROUP);
  }

  public function get_ad_limited_task_stat($start, $end) {
    $DB = $this->get_read_pdo();
    $sql = "SELECT `ad_id`,SUM(`num`) AS `num`,SUM(`rmb`) AS `rmb`,SUM(`ready`) AS `ready`
            FROM `s_limited_task_stat`
            WHERE `task_date`>='$start' AND `task_date`<='$end'
              GROUP BY `ad_id`";
    return $DB->query($sql)->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_UNIQUE|PDO::FETCH_GROUP);
  }
}