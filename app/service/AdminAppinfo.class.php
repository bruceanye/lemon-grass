<?php
/**
 * Created by PhpStorm.
 * User: chensheng
 * Date: 15/9/9
 * Time: 下午4:04
 */

namespace diy\service;

use PDO;

class AdminAppinfo extends Base {
  public function get_apps_detail_and_account($appids) {
    $DB = $this->get_read_pdo();

    $sql = "SELECT a.`id`,a.`appname`,a.`user_id`,`create_time`,u.`account`,a.`app_type`
            FROM `t_appinfo` AS a INNER JOIN `t_user` AS u
            ON a.`user_id`=u.`id`
            WHERE a.`id` IN ('$appids')";
    return $DB->query($sql)->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_UNIQUE);
  }
}