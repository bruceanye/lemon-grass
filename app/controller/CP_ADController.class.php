<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 14/11/17
 * Time: 下午2:01
 */

namespace diy\controller;

use diy\model\ADModel;
use diy\model\DiyUserModel;
use diy\service\AD;
use diy\service\CPTransfer;
use diy\utils\Utils;

class CP_ADController extends BaseController {

  static $LIST_FIELDS = ['id', 'ad_name', 'ad_app_type', 'create_time', 'status', 'transfer', 'today_transfer', 'income', 'max', 'min', 'only'];

  public function __construct() {
    parent::__construct();
    if ($_SESSION['type'] == DiyUserModel::ANDROID_UNION && !in_array('cid', self::$LIST_FIELDS)) {
      self::$LIST_FIELDS[] = 'cid';
    }
  }

  /**
   * 取广告列表
   * @author Meathill
   * @since 0.1.0
   */
  public function get_list() {
    $service =  new AD();

    $pagesize = isset($_REQUEST['pagesize']) ? (int)$_REQUEST['pagesize'] : 10;
    $page = isset($_REQUEST['page']) ? (int)$_REQUEST['page'] : 0;
    $pageStart = $page * $pagesize;
    $order = isset($_REQUEST['order']) ? trim($_REQUEST['order']) : 'create_time';
    $seq = isset($_REQUEST['seq']) ? trim($_REQUEST['seq']) : 'DESC';
    $filters = Utils::array_pick($_REQUEST, ['status', 'ad_name']);
    if ($filters['status'] == 10) {
      $filters['start_time'] = [
        'operator' => '>',
        'data' => date('Y-m-d H:i:s'),
      ];
      unset($filters['status']);
    }
    $filters = array_merge([
      'status' => [0, 1],
      'channel_id' => $_SESSION['channel_id'],
      'ad_app_type' => $_SESSION['type'],
    ], $filters);

    $ads = $service->get_ad_info($filters, $pageStart, $pagesize, [ $order => $seq ] );
    unset($filters['start_time']);
    $online = (int)$service->get_ad_number(array_merge($filters, [
      'status' => 0,
    ]));
    $offline = (int)$service->get_ad_number(array_merge($filters, [
      'status' => 1,
    ]));
    $total = is_array($filters) ? $online + $offline : ($filters['status'] == ADModel::ONLINE ? $online : $offline);
    $ad_ids = array_keys($ads);

    $cp_transfer = CPTransfer::createService($ad_ids);
    $cp_transfer->fetch();
    $ads = $cp_transfer->merge($ads, self::$LIST_FIELDS);

    $this->output(array(
      'code' => 0,
      'msg' => 'get',
      'total' => $total,
      'list' => $ads,
      'numbers' => [
        'total' => $online + $offline,
        'online' => $online,
        'offline' => $offline,
        'status' => is_array($filters['status']) ? '' : (string)$filters['status'],
      ],
    ));
  }

  /**
   * 取新建广告的表单项，修改广告时当前内容
   * @author Meathill
   * @since 0.1.0
   * @param $id
   */
  public function init($id) {
    $ad = new ADModel(['id' => $id]);
    // 广告内容
    if (!$ad->check_owner()) {
      $this->exit_with_error(20, '您无法查询此广告的详细信息', 403);
    }
    $ad->fetch();
    $ad = Utils::array_pick($ad->toJSON(), self::$LIST_FIELDS);

    $this->output(array(
      'code' => 0,
      'msg' => 'fetched',
      'ad' => $ad,
    ));
  }
} 