<?php
/**
 * Created by PhpStorm.
 * User: hemengyuan
 * Date: 15/5/11
 * Time: 下午3:34
 */

namespace diy\service;

use PDO;

class Reward extends Base {
  public function get_sum_reward_by_date($start, $end) {
    $sql = "SELECT sum(`reward`)
            FROM `t_reward`
            WHERE `ctime`>=:start AND `ctime`<=:end AND `ratio`=0";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $state->execute(array(':start' => $start, ':end' => $end . ' 23:59:59'));
    return $state->fetch(PDO::FETCH_COLUMN);
  }
}