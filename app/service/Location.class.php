<?php
/**
 * 处理关于省份的操作
 * User: woddy
 * Date: 14-9-2
 * Time: 下午3:28
 */
namespace diy\service;

use PDO;

class Location extends Base {
  static $PROVINCES = [
    '北京','上海','天津','重庆',
    '广东','黑龙江','吉林','辽宁',
    '河北','河南','内蒙','山西',
    '山东','江苏','浙江','安徽',
    '江西','福建','湖北','湖南',
    '陕西','四川','云南','贵州',
    '广西','海南','宁夏','甘肃',
    '青海','西藏','新疆'
  ];
  
  public function get_provinces_by_ad($adid) {
    $DB = $this->get_read_pdo();
    $sql = "SELECT `province_id`
            FROM `t_ad_province`
            WHERE `ad_id`=:id";
    $state = $DB->prepare($sql);
    $state->execute(array(':id' => $adid));
    $result = $state->fetchAll(PDO::FETCH_COLUMN);
    foreach ( $result as $key => $value ) {
      $result[$key] = (int)$value;
    }
    return $result;
  }
  public function insert_ad_province($id, $provinces) {
    $DB = $this->get_write_pdo();
    $values = array_fill(0, count($provinces), "('$id', ?)");
    $values = implode(',', $values);
    $sql = "INSERT INTO `t_ad_province`
            (`ad_id`, `province_id`)
            VALUES $values";
    $state = $DB->prepare($sql);
    return $state->execute($provinces);
  }

  public function del_by_ad($ad_id) {
    $DB = $this->get_write_pdo();
    $sql = "DELETE FROM `t_ad_province`
            WHERE `ad_id`=:ad_id";
    $state = $DB->prepare($sql);
    return $state->execute(array(
      ':ad_id' => $ad_id,
    ));
  }
}
