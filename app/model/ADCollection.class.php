<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 16/7/1
 * Time: 下午6:53
 */

namespace diy\model;


class ADCollection extends Collection {
  static $table = 't_adinfo';
  static $source = 't_ad_source';

  public function update( array $attr ) {
    list($sets, $to) = $this->parse_filter($attr);
    list($conditions, $params) = $this->parse_filter($this->filters);
    $sql = "UPDATE `t_adinfo` a
              JOIN `t_ad_source` b ON a.`id`=b.`id`
            SET $sets
            WHERE $conditions";
    $DB = $this->get_write_pdo();
    $state = $DB->prepare($sql);
    return $state->execute(array_merge($to, $params));
  }
}