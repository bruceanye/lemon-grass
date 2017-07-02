<?php
/**
 * Created by PhpStorm.
 * User: chensheng
 * Date: 15/8/24
 * Time: 上午10:20
 */
namespace diy\controller;

use diy\model\ADModel;
use diy\service\AD;
use diy\service\AdminAppinfo;
use diy\service\AdminTaskStat;
use diy\service\Auth;
use diy\service\DailyStat;
use diy\service\HappyLockStat;
use diy\service\TransferStat;
use diy\utils\Utils;

class ADStatController extends BaseController {
  public function get_stat_list($ad_app_type) {
    $pagesize = isset($_REQUEST['pagesize']) ? (int)$_REQUEST['pagesize'] : 10;
    $page = isset($_REQUEST['page']) ? (int)$_REQUEST['page'] : 0;
    $page_start = $page * $pagesize;
    $start = $_REQUEST['start'] ? $_REQUEST['start'] : date('Y-m-d');
    $end = $_REQUEST['end'] ? $_REQUEST['end'] : date('Y-m-d');
    $im_cp = $_SESSION['role'] == Auth::$CP_PERMISSION;
    $me = $_SESSION['id'];

    $order = isset($_REQUEST['order']) ? trim($_REQUEST['order']) : 'click';
    $seq = isset($_REQUEST['seq']) ? trim($_REQUEST['seq']) : 'DESC';
    $ad_app_type = strtolower($ad_app_type) == 'ios' ? ADModel::IOS : ADModel::ANDROID;

    $filter = array(
      'ad_app_type' => $ad_app_type,
      'ad_name' => $_REQUEST['ad_name'] ? $_REQUEST['ad_name'] : '',
      'channel' => $_REQUEST['channel'] ? $_REQUEST['channel'] : '',
      'keyword' => $_REQUEST['keyword'] ? $_REQUEST['keyword'] : '',
      ($im_cp ? 'create_user' : 'salesman') => $me
    );
    $filter = array_filter($filter, function ($item) {
      return $item;
    });

    $ad_stat_service = new DailyStat();
    $list = $ad_stat_service->get_ad_stat($filter, $start, $end);
    $total = count($list);

    if ($order) {
      $list = Utils::array_order($list, $order, $seq);
    }
    $list = array_slice($list, $page_start, $pagesize);

    $is_android = (int)$ad_app_type == ADModel::ANDROID ? true : false;
    $this->output(array(
      'code' => 0,
      'msg' => 'get',
      'list' => $list,
      'total' => $total,
      'options' => array(
        'is_android' => $is_android
      )
    ));
  }

  public function get_stat($ad_app_type) {
    $today = date('Y-m-d');
    $start = empty($_REQUEST['start']) ? $today : $_REQUEST['start'];
    $end = empty($_REQUEST['end']) ? $today : $_REQUEST['end'];
    $pagesize = isset($_REQUEST['pagesize']) ? (int)$_REQUEST['pagesize'] : 10;
    $page = isset($_REQUEST['page']) ? (int)$_REQUEST['page'] : 0;
    $page_start = $page * $pagesize;
    $order = isset($_REQUEST['order']) ? trim($_REQUEST['order']) : 'create_time';
    $seq = isset($_REQUEST['seq']) ? trim($_REQUEST['seq']) : 'DESC';
    $im_cp = $_SESSION['role'] == Auth::$CP_PERMISSION;
    $me = $_SESSION['id'];

    $ad_app_type = trim($ad_app_type) == "android" ? ADModel::ANDROID : ADModel::IOS;
    $filters = array(
      'ad_app_type' => $ad_app_type,
      'keyword' => $_REQUEST['keyword'],
      'ad_name' => $_REQUEST['ad_name'],
      'channel' => $_REQUEST['channel'],
      ($im_cp ? 'create_user' : 'salesman') => $me
    );
    $filters = array_filter($filters);
    $ad_service = new AD();
    // 根据广告类型取出所有广告
    $ad_ids = $ad_service->get_ad_ids($filters);
    $result = array(
      'oversea' => false,
      'ads' => array(),
    );
    $total = array(
      'rmb1' => 0,
      'device1' => 0,
      'native' => 0,
      'native_transfer' => 0,
      'task_num' => 0,
      'task_rmb' => 0,
      'task_ready' => 0,
      'cpa' => 0,
      'click' => 0,
      'install' => 0,
      'callback' => 0,
    );

    $ids = array();
    $redis = $this->get_redis();
    $daily_service = new DailyStat();
    for ($date = $start; $date <= $end;) {
      list($ymd, $monthly, $date) = $daily_service->judge_date($date, $end);

      $key = 'diy_ad_stat_install_' . $ymd;
      $value = $redis->get($key);
      if ($value) {
        $redis->setTimeout($key, 86400 * 30);
      } else {
        $value = $daily_service->ad_stat_by_date($ymd, $monthly);
        $value = json_encode($value);
        if ($value && (($monthly || strtotime($ymd) < time() - 3600 * 30))) {
          $redis->setex($key, 86400 * 30, $value);
        }
      }
      $stat = json_decode($value, true);
      foreach ($stat as $ad_stat) {
        $ad_id = $ad_stat['id'];
        if (!in_array($ad_id, $ad_ids)) {
          continue;
        }
        $ids = array_unique(array_merge($ids, array($ad_id)));
        foreach ($total as $key => $value) {
          $result['ads'][$ad_id][$key] += $ad_stat[$key];
          $total[$key] += $ad_stat[$key];
        }
      }
    }

    $adinfos = $ad_service->get_all_ad_info($filters);

    foreach ($ids as $ad_id) {
      if ($adinfos[$ad_id]['oversea']) {
        continue;
      }

      if (!array_key_exists($ad_id, $adinfos)) {
        continue;
      }
      $comments = $ad_service->get_ad_comments_by_id($ad_id);
      $result['ads'][$ad_id] = array_merge($result['ads'][$ad_id], array(
        'id' => $ad_id,
        'channel_id' => $adinfos[$ad_id]['cid'],
        'channel' => $adinfos[$ad_id]['channel'],
        'agreement' => $adinfos[$ad_id]['agreement'],
        'ad_name' => $adinfos[$ad_id]['ad_name'],
        'comments' => $comments,
        'ctime' => date('m-d', strtotime($adinfos[$ad_id]['create_time'])),
        'others' => isset($adinfos[$ad_id]['others']) ? $adinfos[$ad_id]['others'] : '添加注释',
        'sdk_type' => $adinfos[$ad_id]['ad_sdk_type'] == 7 ? 'promotions' : ($adinfos[$ad_id]['ad_sdk_type'] == 2 ? 'push' : ($adinfos[$ad_id]['ad_sdk_type'] == 4 ? 'wap' : 'ad_list not_promotions')),
        'native_type' => $adinfos[$ad_id]['banner_url'] ? 'native' : '',
        'ratio' => $result['ads'][$ad_id]['click'] ? round($result['ads'][$ad_id]['device1'] / $result['ads'][$ad_id]['click'] * 100, 2) : 0,
        'click_ratio' => $result['ads'][$ad_id]['click'] ? round($result['ads'][$ad_id]['cpa'] / $result['ads'][$ad_id]['click'] * 100, 2): 0,
        'task_ratio' => $result['ads'][$ad_id]['task_ready'] ? round($result['ads'][$ad_id]['task_num'] / $result['ads'][$ad_id]['task_ready'] * 100, 2) : 0,
      ));
    }
    // 总计
    $total['ratio'] = $total['click'] ? round($total['transfer'] / $total['click'] * 100, 2) : 0;
    $total['click_ratio'] = $total['click'] ? round($total['cpa'] / $total['click'] * 100, 2) : 0;
    $total['task_ratio'] = $total['task_ready'] ? round($total['task_num'] / $total['task_ready'] * 100, 2) : 0;
    $result['total'] = array_merge(array(
      'id' => 'amount',
      'is_amount' => true
    ), $total);

    $list = array_values($result['ads']);
    $total = count($list);

    // 排序
    $list = $this->get_order_list($list, $order, $seq);

    //分页
    $list = array_slice($list, $page_start, $pagesize);
    if (count($list) > 0) {
      array_push($list, $result['total']);
    }

    $is_android = $ad_app_type == 1 ? true : false;
    $this->output(array(
      'code' => 0,
      'msg' => 'get',
      'list' => $list,
      'total' => $total,
      'options' => array(
        'is_android' => $is_android
      )
    ));
  }

  public function get_order_list($list, $order, $seq) {
    if ($order) {
      if ($seq == 'desc') {
        function build_sorter($order)
        {
          return function ($a, $b) use ($order) {
            if (is_numeric($a[$order])) {
              return $b[$order] - $a[$order];
            }
            return strcmp($b[$order], $a[$order]);
          };
        }

        usort($list, build_sorter($order));
      } else {
        function build_sorter($order)
        {
          return function ($a, $b) use ($order) {
            if (is_numeric($a[$order])) {
              return $a[$order] - $b[$order];
            }
            return strcmp($a[$order], $b[$order]);
          };
        }

        usort($list, build_sorter($order));
      }
    }
    return $list;
  }

  public function get_stat_by_date($id) {
    $today = date('Y-m-d');
    $start = empty($_GET['start']) ? $today : $_GET['start'];
    $end = empty($_GET['end']) ? $today : $_GET['end'];

    $ad_service = new AD();
    $ad = $ad_service->get_ad_info_by_id($id);

    $transferStat_service = new TransferStat();
    $transfer_res = $transferStat_service->get_ad_transfer_by_date($start, $end, $id);
    $happy_lock_transfer = $transferStat_service->get_ad_happy_lock_transfer_by_date($start, $end, $id);

    $adminStat_service = new AdminTaskStat();
    $task = $adminStat_service->get_ad_task_outcome_by_date($start, $end, $id);
    $limited_task = $adminStat_service->get_ad_limited_task_outcome_by_date($start, $end, $id);
    $es_task = $adminStat_service->get_ad_happy_lock_task_by_date($start, $end, $id);
    $es_limited_task = $adminStat_service->get_ad_happy_lock_limited_task_by_date($start, $end, $id);

    $click = $transferStat_service->get_offer_click_total_by_id($start, $end, $id);
    $install = $transferStat_service->get_offer_install_stat_ad_by_id($start, $end, $id);

    if ($ad['ad_app_type'] == 2) {
      $cpa = $transferStat_service->get_ios_cpa_by_ad($start, $end, $id);
    }

    $ymds = array_unique(
      array_merge(
        array_keys((array)$transfer_res),
        array_keys((array)$task),
        array_keys((array)$limited_task),
        array_keys((array)$click),
        array_keys((array)$install)
      )
    );
    sort($ymds);

    $happy_lock_task = array();
    foreach ($es_task['aggregations']['all_dates']['buckets'] as $ad_task) {
      $happy_lock_task[substr($ad_task['key_as_string'], 0, 10)] = $ad_task['sum_rmb']['value'];
    }
    $happy_lock_limited_task = array();
    foreach ($es_limited_task['aggregations']['all_ad_ids']['buckets'] as $ad_task) {
      $happy_lock_limited_task[substr($ad_task['key_as_string'], 0, 10)] = $ad_task['sum_rmb']['value'];
    }

    $result = array_merge($ad, array(
      'start' => $start,
      'end' => $end,
      'cid' => $ad['cid'],
      'channel' => $ad['channel'],
      'ctime' => substr($ad['create_time'], 5, 5),
      'ymd' => array(),
      'rmb1' => 0,
      'device1' => 0,
      'click' => 0,
      'task_rmb' => 0,
      'cpa' => 0,
      'ad_app_type' => $ad['ad_app_type'] == 1 ? 'android' : 'ios',
      'is_ios' => $ad['ad_app_type'] == 2,
      'install' => 0,
      'callback' => 0
    ));

    foreach ($ymds as $key) {
      $ymd = array(
        'date' => $key,
        'rmb1' => ($transfer_res[$key]['rmb'] - $happy_lock_transfer[$key] / 2) / 100,
        'device1' => (int)$transfer_res[$key]['transfer'],
        'click' => (int)$click[$key],
        'task_rmb' => ($task[$key] + $limited_task[$key] - $happy_lock_task[$key] / 2 - $happy_lock_limited_task[$key] / 2) / 100,
        'cpa' => (int)$cpa[$key],
        'ratio' => $cpa && $click ? round($cpa[$key] / $click[$key] * 100, 2) : 0,
        'install' => $install[$key],
      );
      $result['ymd'][] = $ymd;
      $result['rmb1'] += $ymd['rmb1'];
      $result['device1'] += $ymd['device1'];
      $result['click'] += $ymd['click'];
      $result['task_rmb'] += $ymd['task_rmb'];
      $result['cpa'] += $ymd['cpa'];
      $result['install'] += $ymd['install'];
    }
    $result['ratio'] = $result['click'] ? round($result['cpa'] / $result['click'] * 100, 2) : 0;
    $this->output(array(
      'code' => 0,
      'msg' => 'get',
      'list' => $result['ymd']
    ));
  }

  public function get_stat_by_apk($id) {
    $ad_service = new AD();
    $ad = $ad_service->select_ad_join_source_create_time($id);

    $today = date("Y-m-d");
    $start = empty($_GET['start']) ? $today : $_GET['start'];
    $end = empty($_GET['end']) ? $today : $_GET['end'];

    $transfer_service = new TransferStat();
    $transfer_res = $transfer_service->get_app_transfer_by_ad($start, $end, $id);

    $adminStat_service = new AdminTaskStat();
    $task_stat = $adminStat_service->get_ad_task_stat_by_app($start, $end, $id);
    $limited_task_stat = $adminStat_service->get_ad_limited_task_stat_by_app($start, $end, $id);

    $task = array();
    foreach ($task_stat['aggregations']['all_app_ids']['buckets'] as $task_app) {
      if ($task_app['sum_num']['value'] || $task_app['sum_ready']['value']) {
        $task[$task_app['key']['num']] += $task_app['sum_num']['value'];
        $task[$task_app['key']['ready']] += $task_app['sum_ready']['value'];
        $task[$task_app['key']['rmb']] += $task_app['sum_rmb']['value'];
      }
    }
    $limited_task = array();
    foreach ($limited_task_stat['aggregations']['all_app_ids']['buckets'] as $task_app) {
      if ($task_app['sum_num']['value'] || $task_app['sum_ready']['value']) {
        $limited_task[$task_app['key']['num']] += $task_app['sum_num']['value'];
        $limited_task[$task_app['key']['ready']] += $task_app['sum_ready']['value'];
        $limited_task[$task_app['key']['rmb']] += $task_app['sum_rmb']['value'];
      }
    }

    $appids = implode("','",
      array_merge(
        array_keys((array)$transfer_res),
        array_keys($task),
        array_keys($limited_task))
    );

    $appinfo_service = new AdminAppinfo();
    $appinfo = $appinfo_service->get_apps_detail_and_account($appids);

    $result = array_merge($ad, array(
      'start' => $start,
      'end' => $end,
      'ctime' => substr($ad['create_time'], 5, 5),
      'apks' => array(),
      'rmb1' => 0,
      'device1' => 0,
      'ad_app_type' => $ad['ad_app_type'] == 1 ? 'android' : 'ios',
      'task_num' => 0,
      'task_rmb' => 0,
      'task_ready' => 0,
      'limited_task_num' => 0,
      'limited_task_rmb' => 0,
      'limited_task_ready' => 0,
    ));
    foreach ($appinfo as $key => $value) {
      $is_happy_lock = in_array($key, HappyLockStat::HAPPY_LOCK_APP_IDS);
      $apk = array(
        'appid' => $key,
        'appname' => $value['appname'],
        'account' => $value['account'],
        'userid' => $value['user_id'],
        'rmb1' => $is_happy_lock ? $transfer_res[$key]['rmb'] / 100 / 2 : $transfer_res[$key]['rmb'] / 100,
        'device1' => (int)$transfer_res[$key]['transfer'],
        'task_num' => (int)$task[$key]['num'],
        'task_ready' => (int)$task[$key]['ready'],
        'task_rmb' => $is_happy_lock ? $task[$key]['rmb'] / 100 / 2 : $task[$key]['rmb'] / 100,
        'task_ratio' => $task[$key]['ready'] ? (int)($task[$key]['num'] / $task[$key]['ready'] * 100) : 0,
        'limited_task_num' => (int)$limited_task[$key]['num'],
        'limited_task_ready' => (int)$limited_task[$key]['ready'],
        'limited_task_rmb' => $is_happy_lock ? $limited_task[$key]['rmb'] / 100 / 2 : $limited_task[$key]['rmb'] / 100,
        'limited_task_ratio' => $limited_task[$key]['ready'] ? (int)($limited_task[$key]['num'] / $limited_task[$key]['ready'] * 100) : 0,
      );
      $result['apks'][] = $apk;
      $result['rmb1'] += $apk['rmb1'];
      $result['device1'] += $apk['device1'];
      $result['task_num'] += $apk['task_num'];
      $result['task_rmb'] += $apk['task_rmb'];
      $result['task_ready'] += $apk['task_ready'];
      $result['limited_task_num'] += $apk['limited_task_num'];
      $result['limited_task_rmb'] += $apk['limited_task_rmb'];
      $result['limited_task_ready'] += $apk['limited_task_ready'];
    }
    $result['task_ratio'] = $result['task_ready'] ? (int)($result['task_num'] / $result['task_ready'] * 100) : 0;
    $result['limited_task_ratio'] = $result['limited_task_ready'] ? (int)($result['limited_task_num'] / $result['limited_task_ready'] * 100) : 0;

    $this->output(array(
      'code' => 0,
      'msg' => 'msg',
      'list' => $result['apks']
    ));
  }

  public function get_stat_by_loc($id) {
    $ad_service = new AD();
    $ad = $ad_service->select_ad_join_source_create_time($id);

    $today = date("Y-m-d");
    $start = empty($_GET['start']) ? $today : $_GET['start'];
    $end = empty($_GET['end']) ? $today : $_GET['end'];

    $transfer_service = new TransferStat();
    $transfer_res = $transfer_service->get_transfer_location_by_ad($start, $end, $id);

    $result = array(
      'id' => $id,
      'start' => $start,
      'end' => $end,
      'channel' => $ad['channel'],
      'ad_name' => $ad['ad_name'],
      'ctime' => date('m-d', strtotime($ad['create_time'])),
      'cid' => $ad['cid'],
      'countries' => array(),
      'rmb1' => 0,
      'device1' => 0,
      'ad_app_type' => $ad['ad_app_type'] == 1 ? 'android' : 'ios',
    );
    foreach ($transfer_res as $value) {
      $result['countries'][] = array_merge($value, array('rmb' => $value['rmb'] / 100));
      $result['rmb1'] += $value['rmb'] / 100;
      $result['device1'] += $value['transfer'];
    }
    $this->output(array(
      'code' => 0,
      'msg' => 'get',
      'list' => $result
    ));
  }

  public function get_stat_by_hour($id) {
    $ad_service = new AD();
    $ad = $ad_service->select_ad_join_source_create_time($id);

    $today = date("Y-m-d");
    $date = empty($_GET['start']) ? $today : $_GET['start'];

    $transferService = new TransferStat();
    $install_res = $transferService->get_offer_install_stat_ad_h($date, $id);
    $transfer_res = $transferService->get_transfer_stat_ad_h($date, $id);
    $click_res = $transferService->get_offer_click_stat_ad_h($date, $id);
    if ($ad['ad_app_type'] == 2) {
      $cpa_res = $transferService->get_income_transfer_stat_ios_h($date, $id);
    }

    $ymd = explode('-', $date);
    $datestamp = mktime(0, 0, 0, (int)$ymd[1], (int)$ymd[2], (int)$ymd[0]);
    $yesterday = date('Y-m-d', $datestamp - 3600 * 24);
    $tomorrow = date('Y-m-d', $datestamp + 3600 * 24);

    $hours = array_unique(
      array_merge(
        array_keys($transfer_res),
        array_keys($install_res),
        array_keys($click_res)
      )
    );
    if ($ad['ad_app_type'] == 2) {
      $hours = array_unique(
        array_merge($hours, array_keys($cpa_res))
      );
    }
    sort($hours);

    $click = 0;
    $install = 0;
    $transfer = 0;
    $cpa = 0;
    foreach ($hours as $hour) {
      $stat[] = array(
        'hour' => $hour,
        'click' => $click_res[$hour],
        'install' => $install_res[$hour],
        'transfer' => $transfer_res[$hour],
        'cpa' => $cpa_res[$hour],
        'ratio' => $click_res[$hour] ? round($transfer_res[$hour] * 100 / $click_res[$hour], 2) : 0,
        'click_ratio' => $click_res[$hour] ? round($cpa_res[$hour] * 100 / $click_res[$hour], 2) : 0,
      );
      $click += $click_res[$hour];
      $install += $install_res[$hour];
      $transfer += $transfer_res[$hour];
      $cpa += $cpa_res[$hour];
    }
    $ratio = $click == 0 ? 0 : round($transfer * 100 / $click, 2);
    $click_ratio = $click == 0 ? 0 : round($cpa * 100 / $click, 2);
    $transfer_stat = array(
      'hour' => '总计',
      'click' => $click,
      'install' => $install,
      'transfer' => $transfer,
      'cpa' => $cpa,
      'ratio' => $ratio,
      'click_ratio' => $click_ratio,
    );

    $result = array_merge($ad, array(
      'date' => $date,
      'cid' => $ad['cid'],
      'channel' => $ad['channel'],
      'ctime' => substr($ad['create_time'], 5, 5),
      'hour' => $stat,
      'yesterday' => $date < date('Y-m-d', $today - 86400 * 90) ? NULL : $yesterday,
      'tomorrow' => $date == $today ? NULL : $tomorrow,
      'is_ios' => $ad['ad_app_type'] == 2,
      'stat' => $transfer_stat,
    ));
    $this->output(array(
      'code' => 0,
      'msg' => 1,
      'list' => $result['hour']
    ));
  }
}
