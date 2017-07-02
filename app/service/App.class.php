<?php
/**
 * Created by PhpStorm.
 * User: hemengyuan
 * Date: 16/1/4
 * Time: 下午6:28
 */

namespace diy\service;

use PDO;

class App extends Base {
  public function get_user_app_ids($user_ids) {
    list($conditions, $params) = $this->parse_filter(['user_id' => $user_ids]);;
    $sql = "select id
            from t_appinfo
            where $conditions and status=1";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $state->execute($params);
    return $state->fetchAll(PDO::FETCH_COLUMN);
  }
}