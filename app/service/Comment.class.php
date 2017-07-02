<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 15/5/6
 * Time: 下午5:36
 */

namespace diy\service;


use PDO;

class Comment extends Base {
  public function get_comment(array $filters) {
    $DB = $this->get_read_pdo();
    list($conditions, $params) = $this->parse_filter( $filters );
    $sql = "SELECT `ad_id`,`comment`,`NAME` AS `author`,`create_time`
            FROM `t_ad_comment` a LEFT JOIN `t_admin` b ON a.`author`=b.`id`
            WHERE $conditions
            ORDER BY a.`id` DESC";
    $state = $DB->prepare($sql);
    $state->execute($params);
    return $state->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_UNIQUE);
  }

  public function get_diy_comments($id) {
    $DB = $this->get_read_pdo();
    $sql = "SELECT a.`id`, `comment`, `reply`, a.`status`, `create_time`,
              `solve_time`, b.name as author_name, c.name as handle_name
            FROM `t_diy_ad_comment` as a left join t_admin as b on a.author=b.id
              left join t_admin as c on a.handle=c.id
            WHERE `ad_id`=:id";
    $state = $DB->prepare($sql);
    $state->execute(array(':id' => $id));
    $result = $state->fetchAll(PDO::FETCH_ASSOC);
    return $result;
  }
}