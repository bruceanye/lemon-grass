<?php
/**
 * Created by PhpStorm.
 * User: hemengyuan
 * Date: 15/5/20
 * Time: 上午10:54
 */

namespace diy\model;

use diy\service\CompetitorAd;
use diy\utils\Utils;
use Exception;
use SQLHelper;

class CompetitorAdModel extends Base {
  static $FIELDS_MARKET = array('category', 'corp');
  static $FIELDS_DELIVERY = array('owner_manager', 'owner', 'communication', 'delivery', 'delivery_num', 'communication_comment', 'delivery_comment', 'delivery_num_comment', 'important', 'status');

  static $T_MARKET = 't_market_ad_info';
  static $T_DELIVERY = 't_competitor_ads_delivery';

  const COMPLETED = 1;
  const CANCELED = -1;

  public function update(array $attr = null) {
    $ad_service = new CompetitorAd();
    $me = $_SESSION['id'];
    if (!$ad_service->check_delivery_ad_owner($this->id, $me)) {
      throw new Exception('不是您对接的广告，您不能修改', 100);
    }

    //拆分不同表的数据
    if ($attr['status']) {
      $attr['status_editor'] = $me;
      $attr['status_time'] = date("Y-m-d H:i:s");
    }
    $delivery = Utils::array_pick($attr, self::$FIELDS_DELIVERY);

    //更新广告信息
    $DB_write = $this->get_write_pdo();
    $check = SQLHelper::insert_update($DB_write, self::$T_DELIVERY, $delivery, array('pack_name' => $this->id));
    if (!$check) {
      throw new Exception('修改失败', 101);
    }

    $this->attributes = $attr;
    return $attr;
  }
}