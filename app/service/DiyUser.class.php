<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 16/5/20
 * Time: 上午11:41
 */

namespace diy\service;


use PDO;

class DiyUser extends Base {

  public function getUserInfo( array $filters = null ) {
    $DB = $this->get_read_pdo();
    list($conditions, $params) = $this->parse_filter($filters);
    $sql = "SELECT *
            FROM `t_diy_user`
            WHERE $conditions";
    $state = $DB->prepare( $sql );
    $state->execute($params);
    $info = $state->fetch(PDO::FETCH_ASSOC);
    $info['has_export'] = (int)$info['has_export'];
    $info['has_today'] = (int)$info['has_today'];
    return $info;
  }

  public function getOwner( array $filters = null ) {
    $filters = $this->move_field_to($filters, 'id', 'a');
    $DB = $this->get_read_pdo();
    list($conditions, $params) = $this->parse_filter($filters);
    $sql = "SELECT b.`username`
            FROM `t_diy_user` a
              JOIN `t_admin` b ON a.`owner`=b.`id`
            WHERE $conditions";
    $state = $DB->prepare($sql);
    $state->execute($params);
    return $state->fetchColumn();
  }
}