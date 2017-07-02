<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 14/11/17
 * Time: 下午12:40
 */

namespace diy\controller;

use diy\model\ChannelModel;
use diy\service\CPTransfer;
use diy\service\DailyStat;
use diy\model\ADModel;
use diy\service\AD;
use diy\service\AndroidStat;
use diy\service\Auth;
use diy\service\Invoice;
use diy\service\IOS_Stat;
use diy\service\Payment;
use diy\service\Stat;
use diy\service\Transfer;
use diy\service\TransferStat;
use diy\utils\Utils;
use PDO;
use Mustache_Engine;

class StatController extends BaseController {
  /**
   * 全部广告数据统计
   */
  public function get_ad_stat() {
    list( $start, $end, $pagesize, $page_start, $filter ) = $this->getFilter();
    $ad_service = new AD();
    $ad_info = $ad_service->get_all_basic_ad_info($filter);
    $ad_ids = array_unique(array_keys($ad_info));

    if (Auth::is_cp()) { // 广告主暂时都是 iOS
      $service = new TransferStat();
      $transfer = $service->get_iOS_CPA_withKeyword([
        'start' => $start,
        'end' => $end,
        'ad_id' => $ad_ids,
      ]);
      $total = count( $transfer );
      $transfer = array_slice( $transfer, $page_start, $pagesize );
      $transfer = array_map( function ($ad_id) use ($transfer, $ad_info) {
        $ad = $ad_info[$ad_id];
        $ad['id'] = $ad_id;
        $ad['keywords'] = $transfer[$ad_id];
        $ad['count'] = count( $transfer[$ad_id] );
        $ad['total'] = array_sum( array_column( $transfer[ $ad_id ], 'num' ) );
        $ad['has_api'] = (int)$ad['has_api'];
        return $ad;
      }, array_keys( $transfer ) );
      $this->output( [
        'code' => 0,
        'msg' => 'fetched',
        'list' => $transfer,
        'total' => $total,
      ] );
    }

    $service = new Transfer();
    $transfer_res = $service->get_ad_transfer(array(
      'start' => $start,
      'end' => $end,
      'ad_id' => $ad_ids,
    ), 'ad_id');
    // 点击得从两个地方读
    $android = new AndroidStat();
    $click_android = $android->get_ad_click(array(
      'start' => $start,
      'end' => $end,
      'ad_id' => $ad_ids,
    ), 'ad_id');
    $ios = new IOS_Stat();
    $click_ios = $ios->get_ad_click(array(
      'start' => $start,
      'end' => $end,
      'ad_id' => $ad_ids,
    ), 'ad_id');

    $ad_ids = array_unique( array_merge( array_keys( $transfer_res ) ) );
    $total = count( $ad_ids );
    $ad_ids = array_slice( $ad_ids, $page_start, $pagesize );

    $ad_stat = array();
    foreach ($ad_ids as $ad_id) {
      $ad = $ad_info[ $ad_id ];
      $ad = array_merge( $ad, [
        'id' => $ad_id,
        'transfer' => (int)$transfer_res[$ad_id],
        'click' => (int)$click_android[$ad_id] + (int)$click_ios[$ad_id],
        'cost' => $ad['quote_rmb'] * (int)$transfer_res[$ad_id],
      ] );
      $ad_stat[] = $ad;
    }

    $this->output( [
      'code' => 0,
      'msg' => 'fetched',
      'total' => $total,
      'list' => $ad_stat,
    ] );
  }

  /**
   * 单个广告某周期统计
   * @param $id
   */
  public function get_the_ad_stat($id) {
    $ad = new ADModel(['id' => $id]);
    if (!$ad->check_owner()) {
      $this->exit_with_error(20, '您无法查询此广告', 403);
    }
    $ad->fetch();
    list($start, $end) = $this->getFilter($id);
    
    if (Auth::is_cp()) {
      $service = CPTransfer::createService($id);
      $transfer = $service->getADStat($start, $end);
      $this->output( [
        'code' => 0,
        'msg' => 'fetched',
        'list' => $transfer,
      ] );
    }

    $service = new Transfer();
    $transfer_res = $service->get_ad_transfer(array(
      'start' => $start,
      'end' => $end,
      'ad_id' => $id,
    ), 'transfer_date');
    if ($ad->ad_app_type == ADModel::ANDROID) {
      $android = new AndroidStat();
      $click_res = $android->get_ad_click(array(
        'start' => $start,
        'end' => $end,
        'ad_id' => $id,
      ), AndroidStat::TIME_FIELD);
    } else {
      $ios = new IOS_Stat();
      $click_res = $ios->get_ad_click(array(
        'start' => $start,
        'end' => $end,
        'ad_id' => $id,
      ), IOS_Stat::TIME_FIELD);
    }


    $start = strtotime($start);
    $end = strtotime($end);
    $result = array();
    for ($i = $start; $i <= $end; $i += 86400) {
      $date = date('Y-m-d', $i);
      $transfer = (int)$transfer_res[$date];
      if (!$transfer && !$click_res[$date]) {
        continue;
      }
      $result[] = array(
        'ad_id' => $id,
        'date' => $date,
        'transfer' => $transfer,
        'click' => (int)$click_res[$date],
        'cost' => $transfer * $ad->quote_rmb,
      );
    }

    $this->output(array(
      'code' => 0,
      'msg' => 'fetched',
      'total' => count($result),
      'start' => 0,
      'list' => $result,
    ));
  }

  /**
   * 单个广告某天数据统计
   * @param $id
   * @param $date
   */
  public function get_ad_daily_stat($id, $date) {
    $ad = new AD();
    if (!$ad->check_ad_owner($id)) {
      $this->exit_with_error(20, '您无法查询此广告', 401);
    }
    
    if (Auth::is_cp()) {
      $service = new TransferStat();
      $transfer = $service->get_iOS_CPA_withKeywordByHour($id, $date);
      $transfer = array_map( function ( $keywords, $hour ) {
        $oneHour = [
          'hour' => $hour,
          'keywords' => $keywords,
          'count' => count( $keywords ),
          'total' => array_sum( array_column( $keywords, 'num' ) ),
        ];
        return $oneHour;
      }, $transfer, array_keys( $transfer ) );
      $this->output( [ 
        'code' => 0,
        'msg' => 'fetched',
        'list' => $transfer,
      ] );
    }

    $info = $ad->get_ad_info(array('id' => $id), 0, 1);
    $filter = array(
      ':date' => $date,
      ':ad_id' => $id,
    );
    $stat = new Stat();
    $transfer_res = $stat->get_ad_transfer_by_hour($filter);
    $click_res = $stat->get_ad_click_by_hour($filter);

    $result = array();
    for ($hour = 0; $hour < 24; $hour++) {
      if (!$transfer_res[$hour] && !$click_res[$hour]) {
        continue;
      }
      $result[] = array(
        'hour' => $hour,
        'transfer' => (int)$transfer_res[$hour],
        'click' => (int)$click_res[$hour],
        'cost' => $info['quote_rmb'] * (int)$transfer_res[$hour],
      );
    }

    $this->output(array(
      'code' => 0,
      'msg' => 'fetched',
      'total' => 24,
      'start' => 0,
      'list' => $result,
    ));
  }

  /**
   * 全部广告数据分析
   */
  public function get_daily_stat() {
    $start = $_REQUEST['start'];
    $end = $_REQUEST['end'];
    $order = $_REQUEST['order'];
    if ($order) {
      $order = [ $order => $_REQUEST['seq'] ? $_REQUEST['seq'] : 'asc'];
    }
    $page = (int)$_REQUEST['page'];
    $page_size = $_REQUEST['pagesize'] ? (int)$_REQUEST['pagesize'] : 20;
    $filters = array(
      'salesman' => $_SESSION['id'],
      'ad_app_type' => $_REQUEST['ad_app_type'],
      'channel' => $_REQUEST['channel'],
      'ad_name' => $_REQUEST['ad_name'],
      'keyword' => $_REQUEST['keyword']
    );
    $filters = array_filter($filters);

    $daily_service = new DailyStat();
    list($total, $list, $amount) = $daily_service->get_data($start, $end, $filters, $order, $page, $page_size);

    if (count($filters) <= 1) {
      $amount['is_all'] = true;
    }
    $total['amount'] = $amount;
    if (count($list) > $page_size) {
      $list = array_slice($list, $page * $page_size, $page_size);
    }
    $list = $this->is_invoice($list, $start, $end);

    $this->output([
      'code' => 0,
      'msg' => 'fetched',
      'total' => $amount ? $amount['count'] : $total['count'],
      'list' => $list,
      'amount' => $total,
    ]);
  }

  public function is_invoice($ads, $start, $end) {
    $invoiceService = new Invoice();

    $list = array_map(function($item) use ($invoiceService, $start, $end) {
      $nums = $invoiceService->is_invoice($item['ad_id'], $start, $end);
      // 验证开票的条件
      $isInvoice = $item['transfer'] ? $nums > 0 : ($item['cpa'] <= 0);

      // 验证是否对账
      list($paymentChecks, $accountChecks) = $invoiceService->getAccountChecks($start, $end, Payment::CHECK);

      // 先验证按天对账记录,后验证按月对账记录
      $noChecks = Utils::splitDates($start, $end, $accountChecks[$item['ad_id']]);
      $isCheck = count($noChecks) == 0 || in_array($item['ad_id'], $paymentChecks);

      $merges = $isCheck ? ['is_invoice' => $isInvoice, 'is_check' => $isCheck] : ['is_invoice' => $isInvoice, 'is_check' => $isCheck, 'noChecks' => $noChecks];
      $ad = array_merge($item, $merges);
      return $ad;
    }, array_values($ads));
    return $list;
  }

  /**
   * 取单个广告按日数据分析
   * @param $id
   */
  public function get_daily_ad($id) {
    list($start, $end) = $this->getFilter();

    $daily_service = new DailyStat();
    list($result, $adinfo) = $daily_service->get_daily_ad_stat($id, $start, $end);

    $this->output(array(
      'list' => $result,
      'sublist' => $adinfo,
      'code' => 0,
      'msg' => 'ok',
    ));
  }

  public function export_payment() {
    $owner = $_SESSION['id'];
    $filters = array_merge(Utils::array_pick($_REQUEST, ['ad_name', 'ad_app_type', 'keyword', 'channel']), ['salesman' => $owner]);
    $start = $_REQUEST['start'];
    $end   = $_REQUEST['end'];
    $order = $_REQUEST['order'];
    if ($order) {
      $order = [ $order => $_REQUEST['seq'] ? $_REQUEST['seq'] : 'asc'];
    }
    $daily_service = new DailyStat();
    list($total, $list) = $daily_service->get_data($start, $end, $filters, $order);

    $paymentService = new Payment();
    $payments = $paymentService->get_payment_by_owner($start, $end, $owner);

    $adService = new AD();
    $labels = $adService->get_all_labels(PDO::FETCH_KEY_PAIR);

    foreach ($list as $key => $value) {
      $adID = $value['ad_id'];
      $list[$key]['ad_app_type'] = $value['ad_app_type'] == ADModel::ANDROID ? 'Android' : 'iOS';
      $list[$key]['status'] = $value['status'] == ADModel::ONLINE ? 'ON' : 'OFF';
      $list[$key]['channel_type'] = ChannelModel::$TYPE[$value['channel_type']];
      $list[$key]['payment_status'] = '';
      $list[$key]['label'] = $labels[$value['ad_type']];
      foreach (['rmb_out', 'task_out', 'income', 'real_price', 'beprice', 'profit', 'profit_ratio', 'ratio'] as $item) {
        $list[$key][$item] = $value[$item] / 100;
      }
      $list[$key]['quote_rmb'] = isset($value['quote_rmb']['only']) ? ($value['quote_rmb']['only'] / 100) : ($value['quote_rmb']['min'] / 100 . '~' . $value['quote_rmb']['max'] / 100);
      $list[$key]['step_rmb']  = isset($value['step_rmb']['only']) ? ($value['step_rmb']['only'] / 100) : ($value['step_rmb']['min'] / 100 . '~' . $value['step_rmb']['max'] / 100);

      if ($payments[$adID]) {
        foreach ($payments[$adID] as $payment) {
          if (!$payment['payment'] || !$payment['rmb']) {
            $list[$key]['payment_status'] = 'N';
          } else {
            $list[$key]['paid_time'] .= $payment['paid_time'] . ' ' . $payment['payment_person'] . ' ';
            $list[$key]['invoice_time'] .= $payment['invoice_time'] . ' ';
            $list[$key]['invoice_rmb'] .= $payment['invoice_rmb'] . ' ';
            $list[$key]['real_rmb'] .= $payment['real_rmb'] / 100 . ' ';
            $list[$key]['comment'] .= $payment['comment'] . ' ';
          }
        }
      }
    }

    $template = file_get_contents(dirname(__FILE__) . '/../../template/export/' . 'daily.csv');
    $mustache = new Mustache_Engine(['cache' => '/tmp']);
    $csv = $mustache->render($template, ['list' => array_values($list)]);
    $this->output($csv, self::OUTPUT_CSV, "广告数据分析-$start-$end.csv");
  }

  /**
   * @param bool $ad_id
   *
   * @return array
   */
  private function getFilter($ad_id = false) {
    $today      = date( 'Y-m-d' );
    $week       = date( 'Y-m-d', time() - 604800 );
    $start      = empty( $_REQUEST['start'] ) ? $week : $_REQUEST['start'];
    $end        = empty( $_REQUEST['end'] ) ? $today : $_REQUEST['end'];
    $pagesize   = empty( $_REQUEST['pagesize'] ) ? 10 : (int) $_REQUEST['pagesize'];
    $page       = (int) $_REQUEST['page'];
    $page_start = $page * $pagesize;
    $keyword    = $_REQUEST['keyword'];
    $channel    = $_REQUEST['channel'];
    $ad_name    = $_REQUEST['ad_name'];
    $isDianjoy  = (int)$_REQUEST['isDianjoy'];
    $im_cp = Auth::is_cp();

    // 广告主可能不能查看今日数据
    if ($im_cp && !$_SESSION['has_today']) {
      $yesterday = date('Y-m-d', time() - 86400);
      if ($start >= $today) {
        $start = $yesterday;
      }
      if ($end >= $today) {
        $end = $yesterday;
      }
    }

    $filter = array(
      'end'     => $end,
      'keyword' => $keyword,
      'status' => [0, 1],
      'oversea' => 0,
    );
    if ($ad_id) {
      $filter['id'] = $ad_id;
    } else {
      $me    = $_SESSION['id'];
      if ($isDianjoy) {
        $filter['channel_id'] = (int)$_SESSION['channel_id'];
      } else {
        $filter[$im_cp ? 'create_user' : 'salesman'] = $me;
      }
    }
    if ( $channel ) {
      $filter['channel'] = $channel;
    }
    if ( $ad_name ) {
      $filter['ad_name'] = $ad_name;
    }

    return array( $start, $end, $pagesize, $page_start, $filter );
  }
} 