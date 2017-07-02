<?php
namespace diy\controller;

use diy\exception\ADException;
use diy\model\ADModel;
use diy\model\ApplyModel;
use diy\service\AD;
use diy\service\Admin;
use diy\service\Apply;
use diy\service\Notification;
use SQLHelper;

/**
 * Created by PhpStorm.
 * Date: 2014/11/23
 * Time: 23:09
 * @overview 
 * @author Meatill <lujia.zhai@dianjoy.com>
 * @since 
 */

class ApplyController extends BaseController {
  const REDIS_PREFIX = 'diy-apply-edit-on-';

  /**
   * @return Apply
   */
  private function get_service() {
    return new Apply();
  }

  /**
   * 取个人的所有申请
   */
  public function get_list() {
    $me = $_SESSION['id'];
    $keyword = isset($_REQUEST['keyword']) ? trim($_REQUEST['keyword']) : '';
    $page = (int)$_REQUEST['page'];
    $pagesize = isset($_REQUEST['pagesize']) ? (int)$_REQUEST['pagesize'] : 10;
    $start = $page * $pagesize;

    $service = $this->get_service();
    $ad_service = new AD();
    $applies = $service->get_list($me, $keyword, $start, $pagesize);
    $labels = array(
      'set_status' => '上/下线',
      'set_job_num' => '每日限量',
      'set_rmb' => '今日余量',
      'set_ad_url' => '替换包',
      'set_quote_rmb' => '报价',
    );
    $today = date('Y-m-d');
    $expires = array();
    $handler = array();
    $ad_ids = array();

    foreach ( $applies as $index => $apply ) {
      $apply = array_filter($apply, function ($value) {
        return isset($value);
      });
      // 修改每日限额同时修改今日余量
      if (array_key_exists('set_rmb', $apply) && array_key_exists('set_job_num', $apply)) {
        $apply['attr'] = 'set_job_num';
        $apply['label'] = $labels['set_job_num'];
        $apply['after'] = $apply['set_job_num'];
        $apply['extra'] = true;
      } else {
        // 普通处理
        foreach ($apply as $key => $value ) {
          if (preg_match('/^set_\w+/', $key)) {
            $apply['attr'] = $key;
            $apply['label'] = $labels[$key];
            $apply['after'] = $value;
            break;
          }
        }
      }

      if (!$apply['handler'] && $apply['attr']) { // 尚未处理，取之前的值
        $ad_ids[] = $apply['adid'];
      }
      if ($apply['attr'] == 'set_rmb') {
        if ($apply['create_time'] < $today) {
          $expires[] = $apply['id'];
          unset($applies[$index]);
          break;
        }
        $ad_ids[] = $apply['adid'];
      }
      $apply['is_url'] = $apply['attr'] == 'set_ad_url';
      $apply['is_status'] = $apply['attr'] == 'set_status';
      // 没有匹配的值对，则是替换广告
      if (!$apply['attr'] && !$apply['value']) {
        $apply['label'] = '替换广告';
        $apply['after'] = $apply['adid'];
        $apply['is_replace'] = true;
      }

      $handler[] = $apply['handler'];

      $applies[$index] = $apply;
    }

    // 作废申请
    $service->update(array(
      'status' => Apply::EXPIRED
    ), $expires);

    // 取用户姓名
    $handlers = array_filter(array_unique($handler));
    if ($handlers) {
      $admin_service = new Admin();
      $users = $admin_service->get_user_info(array('id' => $handlers));
      foreach ( $applies as $index => $apply ) {
        $applies[$index]['handler'] = isset($users[$apply['handler']]) ? $users[$apply['handler']] : $apply['handler'];
      }
    }

    // 取广告信息然后回填数据
    $ad_info = $ad_service->get_ad_info(array(
      'id' => array_filter(array_unique($ad_ids)),
    ));
    foreach ( $applies as $index => $apply ) {
      if (!$apply['handler'] && $apply['attr']) { // 尚未处理，取之前的值
        $key = substr($apply['attr'], 4);
        $apply['before'] = $ad_info[$apply['adid']][$key];
      }
      $applies[$index] = $apply;
    }

    $total = $service->get_total_number($me, $keyword);
    $this->output(array(
      'code' => 0,
      'msg' => 'fetched',
      'total' => $total,
      'list' => array_values($applies),
    ));
  }

  /**
   * 撤回某个申请
   * @param $id
   */
  public function delete($id) {
    $me = $_SESSION['id'];
    $service = $this->get_service();

    // 禁止操作别人的申请
    if (!$service->is_owner($id, $me)) {
      $this->exit_with_error(10, '您无权操作此申请', 403);
    }

    // 禁止操作已操作的申请
    if (!$service->is_available($id)) {
      $this->exit_with_error(11, '此申请已处理，您不能再次操作', 403);
    }

    // 禁止操作正在处理的申请
    $this->judge_check($id);

    $attr = array(
      'handler' => $me,
      'status' => Apply::WITHDRAWN,
    );
    $check = $service->update($attr, $id);
    if (!$check) {
      $this->exit_with_error(20, '操作失败', 400);
    }

    // 同时撤回相关通知
    $notice = new Notification();
    $notice->set_status(array('uid' => $id), Notification::$HANDLED);

    $this->output(array(
      'code' => 0,
      'msg' => 'deleted',
    ));
  }

  public function update($id) {
    $attr = $this->get_post_data();

    // 禁止用户修改运营正在审核的申请
    $this->judge_check($id);

    // 取当前申请
    $apply_service = new Apply();
    $apply_res = $apply_service->get_by_applyid($id);
    if (!$apply_res) {
      $this->exit_with_error(42, '该申请已经审核过，你不得修改该申请。', 401);
    }

    $apply_res = array_filter($apply_res, function ($value) {
      return isset($value);
    });
    // 修改每日限额同时修改今日余量
    if (array_key_exists('set_rmb', $apply_res) && array_key_exists('set_job_num', $apply_res)) {
      $res['attr'] = 'set_job_num';
    } else {
      // 普通处理
      foreach ($apply_res as $res_key => $value ) {
        if (preg_match('/^set_\w+/', $res_key)) {
          $res['attr'] = $res_key;
          break;
        }
      }
    }

    // 修改量级数据
    if (isset($attr['after'])) {
      if ($res['attr'] == 'set_job_num') {
        $attr = array_merge(array(
          'set_job_num' => $attr['after'],
          'set_rmb' => $attr['after']
        ), $attr);
      } else {
        $attr[$res['attr']] = $attr['after'];
      }
      unset($attr['after']);
    }

    // 修改更换包地址
    if (isset($attr['ad_url'])) {
      $attr = array_merge(array(
        'set_ad_url' => $attr['ad_url']
      ), $attr);

      if (isset($attr['message'])) {
        unset($attr['message']);
      }
      unset($attr['ad_url']);
    }

    $apply = new ApplyModel($id, $attr);
    try {
      $res = $apply->update();
    } catch (ADException $e) {
      $this->exit_with_error($e->getCode(), $e->getMessage(), 400, SQLHelper::$info);
    }

    $this->output(array(
      'code' => 0,
      'msg' => '修改申请成功',
      'apply' => $res
    ));
  }

  public function judge_check($id) {
    $redis = $this->get_redis();
    $redis_key = self::REDIS_PREFIX . $id;
    $value = $redis->get($redis_key);
    if ($value) {
      $value = json_decode($value, true);
      $this->exit_with_error(12, '此申请正由 ' . $value['name'] . ' 处理，请联系ta协助操作。', 403);
    }
  }
} 