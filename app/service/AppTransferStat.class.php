<?php
/**
 * Created by PhpStorm.
 * User: hemengyuan
 * Date: 15/5/11
 * Time: 下午3:23
 */

namespace diy\service;

use PDO;

class AppTransferStat extends Base {
  public function get_real_cost($start, $end, $app_type = 0) {
    $DB = $this->get_read_pdo();
    $app_type = $app_type ? "AND b.app_type='$app_type' " : "";
    $sql = "SELECT SUM(a.rmb_total*`ratio`)*0.01
            FROM s_transfer_stat_app AS a
              LEFT JOIN t_appinfo AS b ON app_id=b.id
              LEFT JOIN t_user AS c ON b.user_id=c.id
              RIGHT JOIN t_reward AS d ON c.account=d.account
            WHERE `ratio`>0 AND transfer_date>=:start AND transfer_date<=:end AND start<=:start AND `end`>=:end $app_type";
    $state = $DB->prepare($sql);
    $state->execute(array(':start' => $start, ':end' => $end));
    $rmb1 = $state->fetch(PDO::FETCH_COLUMN);
    $sql = "SELECT SUM(a.rmb*ratio)*0.01
            FROM s_task_stat_app AS a
              LEFT JOIN t_appinfo AS b ON app_id=b.id
              LEFT JOIN t_user AS c ON b.user_id=c.id
              RIGHT JOIN t_reward AS d ON c.account=d.account
            WHERE ratio>0 AND task_date>=:start AND task_date<=:end AND start<=:start AND `end`>=:end $app_type";
    $state = $DB->prepare($sql);
    $state->execute(array(':start' => $start, ':end' => $end));
    $rmb2 = $state->fetch(PDO::FETCH_COLUMN);
    return round((float)$rmb1 + (float)$rmb2, 2);
  }
}