<?php
namespace diy\controller;

use diy\model\ADModel;
use diy\service\AD;
use diy\service\AdminTaskStat;
use diy\service\Auth;
use diy\service\CPTransfer;
use diy\service\Payment;
use diy\service\Quote;
use diy\service\Transfer;
use diy\service\TransferStat;

/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 14/11/13
 * Time: 下午5:59
 */

class HomeController extends BaseController {
  public function home() {
    echo '<h1>controller ready</h1>';
  }

  public function dashboard() {
    if (Auth::is_cp()) {
      $this->dashboard_cp();
    }
    $this->dashboard_sales();
  }

  public function dashboard_cp() {
    $ad_service = new AD();

    // 取关联的广告
    $ad_info = $ad_service->get_ad_info( [
      'channel_id' => (int)$_SESSION['channel_id'],
      'status' => [ADModel::ONLINE, ADModel::OFFLINE],
      'ad_app_type' => $_SESSION['type'],
    ], 0, 0 );
    $ad_ids = array_keys( $ad_info );

    $service = CPTransfer::createService($ad_ids);
    list($yesterday, $today, $chart) = $service->fetchDashboardData();

    $result = array(
      'code' => 0,
      'msg' => 'ok',
      'data' => array(
        'today' => $today,
        'today_transfer' => (int)$today,
        'yesterday' => $yesterday,
        'yesterday_transfer' => (int)$yesterday,
        'chart' => $chart,
      ),
    );
    $this->output($result);
  }

  public function dashboard_sales() {
    $me = $_SESSION['id'];
    $my_name = $_SESSION['fullname'];

    // 计算日期
    list($today, $yesterday, $start, $end, $month_start, $season_start, $months) = $this->calculate_date();

    $ad_service = new AD();
    $pack_name_count = $ad_service->get_online_packname_count();

    $transfer_service = new TransferStat();
    $last_hour_ios_click = $transfer_service->get_last_hour_ios_click();
    $last_hour_ios_transfer = $transfer_service->get_last_hour_ios_transfer();

    // 本月数据*4 + 我的广告
    list($income_total, $out_total, $stat_total, $cpa_total) = $this->get_total_data($start, $end, $me);

    // 公司营收曲线 + cpa比 + 渠道比
    list($corp, $corp_stat, $corp_cpa) = $this->get_corp_data($start, $end, $me);

    // 回款和发票
    list($ok, $total, $invoice, $payment_rmb) = $this->get_payment_data($start, $end, $me);

    $result = array(
      'me' => $me,
      'my_name' => $my_name,
      'today' => $today,
      'yesterday' => $yesterday,
      'start' => $start,
      'end' => $end,
      'month_start' => $month_start,
      'season_start' => $season_start,
      'months' => $months,
      'income' => $income_total / 100,
      'out' => $out_total / 100,
      'stat' => $stat_total,
      'cpa' => $cpa_total,
      'ratio' => $stat_total != 0 ? round($cpa_total / $stat_total * 100, 2) : 0,
      'profit' => $out_total != 0 ? round(($income_total * 0.928 - $out_total * 1.2) / $out_total * 100, 2) : 0,
      'corp_transfer' => array_values($corp),
      'corp_stat' => $corp_stat,
      'stat_percent' => $corp_stat > 0 ? round($stat_total / $corp_stat * 100, 2) : 0,
      'corp_cpa' => $corp_cpa,
      'cpa_percent' => $corp_cpa ? round($cpa_total / $corp_cpa * 100, 2) : 0,
      'payment' => $total > 0 ? round($ok / $total * 100, 2) : 0,
      'invoice' => $total > 0 ? round($invoice / $total * 100, 2) : 0,
      'payment_ratio' => $income_total > 0 ? round($payment_rmb / $income_total * 100, 2) : 0,
      'pack_name_count_android' => (int)$pack_name_count['android'],
      'pack_name_count_ios' => (int)$pack_name_count['ios'],
      'ios_ratio' => $last_hour_ios_click ? round($last_hour_ios_transfer / $last_hour_ios_click * 100, 2) : 0,
      'is_sale' => true,
    );

    $this->output(array(
      'code' => 0,
      'msg' => 'get',
      'data' => $result
    ));
  }

  private function get_total_data($start, $end, $me) {
    $ad_service = new AD();
    $filters = array(
      'salesman' => $me
    );
    $ad_info = $ad_service->get_all_basic_ad_info($filters);
    $adids = array_keys((array)$ad_info);

    $transfer_service = new TransferStat();
    $transfer = $transfer_service->get_ad_transfer_by_ads($start, $end, '');

    $quote_service = new Quote();
    $adquote = $quote_service->get_ad_quote_by_owner($start, $end, $me);

    $adminTask_service = new AdminTaskStat();
    $task = $adminTask_service->get_ad_task_outcome_by_sale($start, $end, $me);

    // 本月数据*4 + 我的广告
    $income_total = 0;
    $out_total = 0;
    $stat_total = 0;
    $cpa_total = 0;
    foreach ($adids as $adid) {
      if (!in_array($adid, $adids)) {
        continue;
      }
      $income = (int)$adquote[$adid]['income'];
      $out = (int)$transfer[$adid]['rmb'] + (int)$task[$adid];
      $stat = (int)$transfer[$adid]['transfer'];
      $cpa = (int)$adquote[$adid]['cpa'];
      $income_total += $income;
      $out_total += $out;
      $stat_total += $stat;
      $cpa_total += $cpa;
    }

    return array($income_total, $out_total, $stat_total, $cpa_total);
  }

  private function get_corp_data($start, $end, $me) {
    $transfer_service = new TransferStat();
    $my_transfer = $transfer_service->get_ad_transfer_by_sale($start, $end, $me);
    $corp_transfer = $transfer_service->get_transfer_by_app_day($start, $end, "");

    $quote_service = new Quote();
    $corp_quote = $quote_service->get_by_ads($start, $end, "", true);

    $corp = array();
    $corp_stat = 0;
    foreach ($corp_transfer as $date => $value) {
      $item = array(
        'date' => $date,
        'transfer' => (int)$value['transfer'],
        'my_transfer' => (int)$my_transfer[$date]['transfer'],
      );
      $corp_stat += (int)$value['transfer'];
      $corp[] = $item;
    }
    array_pop($corp); // 不要画今天的点
    $corp_cpa = 0;
    foreach ($corp_quote as $value) {
      $corp_cpa += (int)$value['cpa'];
    }

    return array($corp, $corp_stat, $corp_cpa);
  }

  private function get_payment_data($start, $end, $me) {
    $payment_service = new Payment();
    $payments = $payment_service->get_payment_by_owner($start, $end, $me);
    
    $ok = $invoice = $payment_rmb = 0;
    $total = count($payments);
    foreach ($payments as $key => $values) {
      foreach ($values as $payment) {
        $ok += $payment['payment'];
        $invoice += $payment['invoice'];
        $payment_rmb += $payment['rmb'];
      }
    }

    return array($ok, $total, $invoice, $payment_rmb);
  }

  private function calculate_date() {
    $time = time();
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', $time - 86400);
    $start = isset($_GET['start']) && $_GET['start'] ? $_GET['start'] : date('Y-m-d', mktime(0, 0, 0, (int)date('m'), 1, date('Y')));
    $end = isset($_GET['end']) && $_GET['end'] != '' ? $_GET['end'] : $today;
    $season = ((int)date('m') - 1) / 3 >> 0;
    $month_start = date('Y-m-d', mktime(0, 0, 0, date('m'), 1, date('Y')));
    $season_start = date('Y-m-d', mktime(0, 0, 0, $season * 3 + 1, 1, date('Y')));
    $months = array();
    for ($i = 0; $i < 3; $i++) {
      $month = (int)date('m') - $i - 1;
      $months[] = array(
        'month' => $i != 0 ? ($month <= 0 ? $month + 12 : $month) . '月份' : '上个月',
        'start' => date('Y-m-d', mktime(0, 0, 0, $month, 1, (int)date('Y', $time))),
        'end' => date('Y-m-d', mktime(0, 0, 0, $month + 1, 0, (int)date('Y', $time))),
      );
    }
    return array($today, $yesterday, $start, $end, $month_start, $season_start, $months);
  }
} 