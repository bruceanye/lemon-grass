<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 16/7/14
 * Time: 下午3:57
 */

namespace diy\model;


use diy\service\Diy;
use diy\utils\Utils;
use Exception;
use SQLHelper;

/**
 * @property string ad_name
 * @property int ad_app_type
 * @property int put_ipad
 * @property int status
 * @property int quote_rmb
 * @property int is_api
 * @property int cate
 * @property int settle_type
 */
class DiyModel extends Base {
  const WAIT = 0;
  const IN_REVIEW = 1;
  const SUCCESS = 2;
  const FAILED = 3;
  const DELETE = 100;
  const LABELS = ['新建', '审核中', '审核通过', '审核失败'];

  protected static $TABLE = 't_adinfo_diy';
  protected static $DETAIL = 't_adinfo_diy_detail';

  public function fetch(  ) {
    $service = new Diy();
    $attr = $service->getDiy(['id' => $this->id])[0];
    $attr['plans'] = $service->getDiyDetail(['diy_id' => $this->id]);
    $attr['quote_rmb'] /= 100;
    $this->attributes = $attr;
    return $attr;
  }

  public function save( array $attr = null ) {
    $attr = $this->validate($attr);
    list($attr, $plans) = $this->split($attr);

    $this->id = $attr['id'] = Utils::create_id();
    $attr['ad_app_type'] = ADModel::IOS;
    $attr['owner'] = $_SESSION['id'];
    $attr['create_time'] = date('Y-m-d H:i:s');
    $attr['status'] = DiyModel::WAIT;
    $DB = $this->get_write_pdo();
    $check = SQLHelper::insert( $DB, self::$TABLE, $attr );
    if (!$check) {
      throw new Exception('创建投放计划失败', 10);
    }

    $check_plans = $this->createPlans( $plans );
    if (!$check_plans) {
      throw new Exception('创建投放计划细节失败', 20);
    }

    $attr['plans'] = $plans;
    $this->attributes = $attr;
    return $attr;
  }

  public function update(array $attr = null) {
    $attr = $this->validate($attr);
    list($attr, $plans) = $this->split($attr);
    if (!array_key_exists('status', $attr)) {
      $attr['status'] = DiyModel::WAIT; // 修改后设置状态为0
    }

    $DB = $this->get_write_pdo();
    $check = SQLHelper::update($DB, self::$TABLE, $attr, $this->id);
    if ($check === false) {
      throw new Exception('修改投放计划失败', 10);
    }

    if ($plans) {
      SQLHelper::delete($DB, self::$DETAIL, ['diy_id' => $this->id]);
      $check_plans = $this->createPlans($plans);
      if (!$check_plans) {
        throw new Exception('修改投放计划细节失败', 20);
      }
    }

    $this->attributes = array_merge($this->attributes, $attr);
    return $attr;
  }

  public function validate( array $attr = null ) {
    $attr = parent::validate($attr);
    if ($attr['quote_rmb']) {
      $attr['quote_rmb'] *= 100;
    }
    return $attr;
  }

  public function checkOwner() {
    $me = $_SESSION['id'];
    $service = new Diy();
    return $service->checkOwner($this->id, $me);
  }

  private function split( $attr ) {
    $plans = [];
    $attr = array_filter($attr, function ($value, $key) use (&$plans) {
      if (preg_match('/^(start_time|end_time|keyword|num)-c(\d+)$/', $key, $matches)) {
        $plans[$matches[2]][$matches[1]] = $value;
        return false;
      }
      return true;
    }, ARRAY_FILTER_USE_BOTH);
    if ($plans) {
      $range = array_reduce($plans, function ($memo, $item) {
        $memo['start_time'] = min($memo['start_time'], $item['start_time']);
        $memo['end_time'] = max($memo['end_time'], $item['end_time']);
        return $memo;
      }, ['start_time' => '3', 'end_time' => '']);
      $attr = array_merge($attr, $range);
      $plans = array_values($plans);
    }
    return [$attr, $plans];
  }

  /**
   * @param $plans
   *
   * @return bool|int
   */
  private function createPlans( $plans ) {
    $plans       = array_map( function ( $plan ) {
      $plan['diy_id'] = $this->id;
      return $plan;
    }, $plans );
    return SQLHelper::insert_multi( $this->DB_write, self::$DETAIL, $plans );
  }
}