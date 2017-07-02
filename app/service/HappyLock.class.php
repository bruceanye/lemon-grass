<?php
/**
 * Created by PhpStorm.
 * User: hemengyuan
 * Date: 15/7/15
 * Time: 下午3:59
 */

namespace diy\service;


use diy\service\Base;
use Exception;
use PDO;
use SQLHelper;

class HappyLock extends Base {
  static $CHANNELS = array(
    '70' => '百度',
    '87' => '多盟Android',
    '97' => '多盟iOS',
  );

  public function get_channel_white_ads() {
    $DB  = $this->get_read_pdo();
    $sql = "select id,channel_id,ad_id,app_name,money from m_3p_black_white_list";
    $ads = $DB->query( $sql )->fetchAll(PDO::FETCH_ASSOC);

    return $ads;
  }

  public function add_white_ads($ad_id, $platform) {
    $DB_write = $this->get_write_pdo();
    $result = [];
    $now = date("Y-m-d H:i:s");
    foreach ($ad_id as $value) {
      $item = explode(',', $value);
      $result[] = [
        'ad_id' => $item[0],
        'app_name' => $item[1],
        'android_ios' => 1,
        'create_time' => $now,
        'channel_id' => $platform,
        'money' => $item[2],
      ];
    }
    if (!SQLHelper::insert_multi($DB_write, 'm_3p_black_white_list', $result)) {
      throw new Exception('添加失败', 100);
    }

    $last_id = (int)$DB_write->lastInsertId();
    foreach ($result as $key => $value) {
      $result[$key]['id'] = $last_id + $key;
    }

    return $result;
  }

  public function get_channel_ads($channel_id) {
    $data = file_get_contents(INNER_SERVICE . "inner_api/third_party_ads/$channel_id");
    $data = json_decode($data, true);
    $ads = $data['data'];

    $ad_service = new AD();
    $pack_names = $ad_service->get_online_pack_names();

    $DB    = $this->get_read_pdo();
    $sql   = "select ad_id from m_3p_black_white_list where channel_id=:channel_id";
    $state = $DB->prepare($sql);
    $state->execute([':channel_id' => $channel_id]);
    $white_ads = $state->fetchAll(PDO::FETCH_COLUMN);

    foreach ($ads as $key => $value) {
      if (in_array($value['pack_name'], $white_ads)) {
        unset($ads[$key]);
        continue;
      }
      $ads[$key]['own'] = in_array($value['pack_name'], $pack_names);
    }

    $ads = array_values($ads);
    return $ads;
  }

  public function delete_white_ads($id) {
    $DB_write = $this->get_write_pdo();
    $sql = "delete from m_3p_black_white_list where id=:id";
    $state = $DB_write->prepare($sql);
    if (!$state->execute(array(':id' => $id))) {
      throw new Exception('删除失败', 101);
    }
  }
}