<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 16/5/20
 * Time: 上午11:38
 */

namespace diy\model;


use diy\service\DiyUser;
use Exception;
use SQLHelper;

/**
 * @property int corp 广告主id
 */
class DiyUserModel extends Base {
  const TABLE = 't_diy_user';

  const CATES = ['联网激活', '注册登录'];
  const SETTLE_CYCLES = ['预付', '双周付', '月付', '季付'];
  const SETTLE_TYPES = [4 => '以 API 接口回调数据结算', 7 => '以点乐数据买量结算，保结算不核减', 10 => '点乐数据买量，次日核对idfa'];

  const ALLOW_STATUS = 0;
  const BAN_STATUS = 1;
  const DELETE_STATUS = 2;

  const ANDROID_UNION = 1;
  const IOS_CP = 2;
  
  public function fetch( ) {
    $service = new DiyUser();
    $attr = null;
    if ($this->id) {
      $attr = $service->getUserInfo(['id' => $this->id]);
    } else if ($this->corp) {
      $attr = $service->getUserInfo(['corp' => $this->corp]);
      if ($attr) {
        $this->id = $attr['id'];
      }
    }
    if ($attr) {
      $this->attributes = $attr;
    }
  }

  public function update( array $attr = null ) {
    $attr = $this->validate($attr);
    $DB = $this->get_write_pdo();
    
    $result = SQLHelper::update( $DB, self::TABLE, $attr, $this->id, false );
    if (!$result) {
      throw new Exception('更新自助后台用户信息失败', 10);
    }
    $this->attributes = array_merge( $this->attributes, $attr );
    return $this;
  }
}