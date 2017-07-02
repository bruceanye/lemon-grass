<?php
namespace diy\controller;

use DateTime;
use diy\model\ADModel;
use diy\service\AD;
use diy\service\AndroidStat;
use diy\service\Apply;
use diy\service\DailyStat;
use diy\service\Job;
use diy\service\Payment;
use diy\service\Quote;
use diy\service\Transfer;
use diy\utils\Utils;

/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 15/2/11
 * Time: 下午6:57
 */

class HistoryInfo extends BaseController {
  public function __construct( $need_auth = true ) {
    $this->need_auth = $need_auth;
    parent::__construct();
  }

  public function get_list($output = true) {
    $query = trim($_REQUEST['keyword']);
    if (!$query) {
      $this->output(array(
        'code' => 0,
        'msg' => '没有关键词',
      ));
    }

    // 取广告，100个基本等于不限
    $season = date('Y-m-d', time() - 86400 * 90);
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', time() - 86400);
    $service = new AD();
    $transfer = new Transfer();
    $ads = $service->get_ad_info(array(
      'keyword' => $query,
      'status' => array(0, 1),
      'oversea' => 0,
      'ad_app_type' => 1,
      'ad_sdk_type' => 1,
    ), 0, 100, ['create_time' => 'DESC']);
    $ad_ids = array_keys($ads);

    // 取下线申请
    $apply_service = new Apply();
    $applies = $apply_service->get_offline_apply(array('adid' => $ad_ids));

    // 取广告结算状态
    $payment_service = new Payment();
    $quote_service = new Quote();
    $payments = $payment_service->get_payment($ad_ids, $season, $today);
    $quotes = $quote_service->get_quote($ad_ids, $season, $today);
    foreach ( $payments as $payment ) {
      $ad_id = $payment['id'];
      $month = substr($payment['month'], 0, 7);
      $ads[$ad_id]['payment'] += (int)$payment['rmb'];
      $ads[$ad_id]['quote'] += (int)$quotes[$ad_id][$month];
    }

    // 取饱和度
    $pack_names = [];
    foreach ( $ads as $ad ) {
      if (!in_array($ad['pack_name'], $pack_names)) {
        $pack_names[] = $ad['pack_name'];
      }
    }
    $pack_names = array_filter($pack_names);
    $job = new Job();
    $yesterday_job = $job->get_log(array(
      'pack_name' => $pack_names,
      'ad_sdk_type' => 1,
      'start' => $yesterday,
      'end' => $today,
    ));
    $yesterday_done = $transfer->get_ad_transfer(array(
      'pack_name' => $pack_names,
      'ad_sdk_type' => 1,
      'date' => $yesterday,
    ), 'pack_name');
    $approach_satiate = $transfer->get_approach_satiate([
      'pack_name' => $pack_names,
      'ad_sdk_type' => 1,
      'start' => $yesterday,
      'end' => $today,
    ]);
    $delivery = array();
    foreach ( $yesterday_job as $pack_name => $job_num ) {
      $delivery[$pack_name] = $this->parse_history($job_num, $yesterday_done[$pack_name], in_array($pack_name, $approach_satiate));
    }

    // 取点评记录
    $pack_info = array();
    foreach ( $ads as $ad ) {
      $pack_info[$ad['pack_name']] = $ad['ad_name'];
    }
    $pack_names = array_unique(array_filter(array_keys($pack_info)));
    $comments_by_pack_name = array();
    if ($pack_names) {
      $comments = $service->get_comments(array('pack_name' =>$pack_names));
      foreach ( $comments as $comment ) {
        $pack_name = $comment['pack_name'];
        $array = $comments_by_pack_name[$pack_name];
        $array = is_array($array) ? $array : array(
          'ad_name' => $pack_info[$pack_name],
          'pack_name' => $pack_name,
          'comments' => array(),
        );
        $array['comments'][] = $comment;
        $comments_by_pack_name[$pack_name] = $array;
      }
    }

    // 取转化率,只取最近3~4个月的数据,之前的认为不可靠
    $daily = new DailyStat();
    $date = new DateTime();
    $end = $date->format('Y-m-d');
    $date->modify('first day of -3 month');
    $start = $date->format('Y-m-d');
    $daily_data = $daily->get_data($start, $end, ['ad_id' => $ad_ids])[1];

    $result = array();
    foreach ( $ads as $key => $ad ) {
      $item = Utils::array_pick($ad, 'ad_name', 'others', 'create_time', 'quote_rmb', 'payment', 'quote', 'feedback');
      $item['id'] = $key;
      $item['transfer'] = (int)$daily_data[$key]['transfer'];
      $item['ratio'] = $item['transfer'] ? $daily_data[$key]['cpa'] / $item['transfer'] : 0;
      $item['payment_percent'] = $item['quote'] != 0 ? round($item['payment'] / $item['quote'] * 100, 2) : 0;
      $item['offline_msg'] = $applies[$key];
      $item = array_merge($item, (array)$delivery[$ad['pack_name']]);

      $result[] = $item;
    }

    // 按照回款率第一，下线请求，有无备注，有无推广的优先级进行排序
    usort($result, function ($a, $b) {
      if ($a['payment_percent'] != $b['payment_percent']) {
        return $a['payment_percent'] < $b['payment_percent'] ? 1 : -1;
      }
      if (($a['offline_msg'] && !$b['offline_msg']) || (!$a['offline_msg'] && $b['offline_msg'])) {
        return $a['offline_msg'] ? -1 : 1;
      }
      if (($a['others'] && !$b['others']) || (!$a['others'] && $b['others'])) {
        return $a['others'] ? -1 : 1;
      }
      if ($a['transfer'] != $b['transfer']) {
        return $b['transfer'] - $a['transfer'];
      }
      return strcmp($a['create_time'], $b['create_time']);
    });

    $result = [
      'code' => 0,
      'msg' => 'fetch',
      'list' => array_slice($result, 0, 20),
      'ad_comments' => array_values($comments_by_pack_name),
    ];
    if ($output) {
      $this->output($result);
    }
    return $result;
  }

  private function parse_history( $job_num, $transfer, $is_approach ) {
      return [
        'is_full' => $job_num > $transfer * 1.1 && $job_num - $transfer > 100,
        'fullness' => $is_approach
      ];
  }
}