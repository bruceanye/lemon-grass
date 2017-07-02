<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 16/7/14
 * Time: 下午4:20
 */

namespace diy\service;


use PDO;

class Diy extends Base {

  public function getDiy( array $filters = null, $page = 0, $size = 0, $order = null ) {
    list($conditions, $params) = $this->parse_filter($filters);
    $start = $page * $size;
    $limit = $size ? "LIMIT $start,$size" : '';
    $order = $order ? 'ORDER BY ' . $this->get_order($order) : '';
    $sql = "SELECT `id`,`ad_name`,`ad_url`,`total_num`,`status`,`ad_app_type`,
              `start_time`,`end_time`,`quote_rmb`,`put_ipad`,`owner`,`create_time`,
              `is_api`,`cate`,`settle_type`
            FROM `t_adinfo_diy`
            WHERE $conditions
            $order
            $limit";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $state->execute($params);
    return $state->fetchAll(PDO::FETCH_ASSOC);
  }

  public function getDiyDetail( array $filters = null ) {
    list($conditions, $params) = $this->parse_filter($filters);
    $sql = "SELECT `id`,`diy_id`,`start_time`,`end_time`,`keyword`,`num`
            FROM `t_adinfo_diy_detail`
            WHERE $conditions";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $state->execute($params);
    return $state->fetchAll(PDO::FETCH_ASSOC);
  }

  public function count( array $filters = null ) {
    list($conditions, $params) = $this->parse_filter($filters);
    $sql = "SELECT COUNT('X')
            FROM `t_adinfo_diy`
            WHERE $conditions";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $state->execute($params);
    return $state->fetchColumn();
  }

  public function checkOwner( $id, $me ) {
    $sql = "SELECT 'x'
            FROM `t_adinfo_diy`
            WHERE `id`=:id AND `owner`=:me";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $state->execute([
      ':id' => $id,
      ':me' => $me,
    ]);
    return $state->fetchColumn();
  }
}