<?php
/**
 * Created by PhpStorm.
 * User: chensheng
 * Date: 15/7/2
 * Time: 下午3:22
 */

namespace diy\service;

use dianjoy\BetterDate;
use diy\model\ADModel;
use diy\utils\Utils;
use Exception;
use Mustache_Engine;
use PDO;

class DailyStat extends Base {
  const STAT_MERGE_KEYS = [ 'income', 'transfer', 'cpa', 'out', 'rmb_out', 'task_out', 'activity_out', 'publisher_out', 'activity_income', 'cut', 'happy_lock_rmb', 'publisher_transfer', 'publisher_cpa', 'publisher_income', 'publisher_cut', 'quoted_transfer' ];

  static $defaults = [
    'cpc' => 0,
    'click' => 0,
    'out' => 0,
    'transfer' => 0,
    'cpa' => 0,
    'income' => 0,
    'rmb_out' => 0,
    'task_out' => 0,
    'activity_out' => 0,
    'activity_income' => 0,
    'happy_lock_transfer' => 0,
    'happy_lock_rmb' => 0,
    'happy_lock_income' => 0,
    'other_transfer' => 0,
    'other_rmb' => 0,
    'cut' => 0,
    'count' => 0,
    'ad_ids' => [],
  ];

  protected $DB_daily;

  public function get_ad_stat($filter, $start, $end) {
    $ad_service = new AD();
    $ads = $ad_service->get_ad_info($filter);

    $ad_transfer_service = new ADTransferStat();
    $transfer_res = $ad_transfer_service->get_ad_transfer_stat($start, $end);

    $transfer_stat_service = new TransferStat();
    $happy_lock_transfer = $transfer_stat_service->get_all_ad_happy_lock_transfer($start, $end);

    $task_stat_service = new TaskStat();
    $task = $task_stat_service->get_ad_task_stat($start, $end);
    $limited_task = $task_stat_service->get_ad_limited_task_stat($start, $end);

    $happylock_stat_service = new HappyLockStat();
    $happy_lock_task = $happylock_stat_service->get_all_ad_happy_lock_task($start, $end, TaskStat::TASK_ES_TYPE);
    $happy_lock_limited_task = $happylock_stat_service->get_all_ad_happy_lock_task($start, $end, TaskStat::LIMITED_TASK_ES_TYPE);

    $native_stat_service = new NativeStat();
    $native = $native_stat_service->get_native_stat_ad($start, $end);
    $native_transfer = $native_stat_service->get_native_transfer_stat_by_ad($start, $end);

    $click = $transfer_stat_service->get_offer_click_total($start, $end);
    $install = $transfer_stat_service->get_offer_install_stat_ad($start, $end);
    $callback = $transfer_stat_service->get_income_stat_ios(['start' => $start, 'end' => $end]);
    $cpa = $transfer_stat_service->get_ios_cpa_by_day($start, $end);

    $value = array();
    foreach ($ads as $id => $ad) {
      $ad_id = $id;
      $stat = array(
        'id' => $ad_id,
        'cid' => $ad['cid'],
        'channel' => $ad['channel'],
        'ad_name' => $ad['ad_name'],
        'ad_app_type' => $ad['ad_app_type'],
        'rmb1' => round((isset($transfer_res[$ad_id]) ?
            ($transfer_res[$ad_id]['rmb'] - $happy_lock_transfer[$ad_id]['rmb'] / 2) / 100 : 0) +
          ($task[$ad_id]['rmb'] + $limited_task[$ad_id]['rmb'] - $happy_lock_task[$ad_id] / 2 -
            $happy_lock_limited_task[$ad_id] / 2) / 100, 2),
        'device1' => (int)$transfer_res[$ad_id]['transfer'],
        'native' => $native[$ad_id],
        'native_transfer' => $native_transfer[$ad_id],
        'task_num' => $task[$ad_id]['num'] + $limited_task[$ad_id]['num'],
        'task_rmb' => ($task[$ad_id]['rmb'] + $limited_task[$ad_id]['rmb'] - $happy_lock_task[$ad_id] / 2 - $happy_lock_limited_task[$ad_id] / 2) / 100,
        'task_ready' => $task[$ad_id]['ready'],
        'click' => (int)$click[$ad_id],
        'install' => $install[$ad_id],
        'callback' => (int)$callback[$ad_id],
        'cpa' => (int)$cpa[$ad_id],
        'ratio' => $click[$ad_id] ? round($transfer_res[$ad_id]['transfer'] / $click[$ad_id] * 100, 2) : 0,
        'click_ratio' => $click[$ad_id] ? round($cpa[$ad_id] / $click[$ad_id] * 100, 2) : 0,
        'task_ratio' => $task[$ad_id]['ready'] ? round(($task[$ad_id]['num'] + $limited_task[$ad_id]['num']) / $task[$ad_id]['ready'] * 100, 2) : 0,
        'callback_ratio' => $install[$ad_id] ? round((int)$cpa[$ad_id] / $install[$ad_id] * 100, 2) : 0
      );
      array_push($value, $stat);
    }
    return $value;
  }

  public function ad_stat_by_date($ymd, $monthly) {
    $value = array();

    if ($monthly) {
      $start = $ymd . '-01';
      $end = date('Y-m-d', mktime(0, 0, 0, substr($ymd, 5, 2) + 1, 0, substr($ymd, 0, 4)));
    } else {
      $start = $ymd;
      $end = $ymd;
    }

    $transferStat_service = new TransferStat();
    $transfer_res = $transferStat_service->get_ad_transfer_by_ads($start, $end, "");
    $happy_lock_transfer = $transferStat_service->get_all_ad_happy_lock_transfer($start, $end);
    $click = $transferStat_service->get_offer_click_total($start, $end);
    $cpa = $transferStat_service->get_ios_cpa_by_day($start, $end);

    $adminStat_service = new AdminTaskStat();
    $task = $adminStat_service->get_ad_task_stat($start, $end);
    $limited_task = $adminStat_service->get_ad_limited_task_stat($start, $end);
    $es_task = $adminStat_service->get_all_ad_happy_lock_task($start, $end);
    $es_limited_task = $adminStat_service->get_all_ad_happy_lock_limited_task($start, $end);

    $nativeStat_service = new NativeStat();
    $native = $nativeStat_service->get_native_stat_ad($start, $end);
    $native_transfer = $nativeStat_service->get_native_transfer_stat_by_ad($start, $end);

    $callback = $transferStat_service->get_income_stat_ios(['start' => $start, 'end' => $end]);
    $install = $transferStat_service->get_offer_install_stat_ad($start, $end);

    $happy_lock_task = array();
    foreach ($es_task['aggregations']['all_ad_ids']['buckets'] as $ad_task) {
      $happy_lock_task[$ad_task['key']] = $ad_task['sum_rmb']['value'];
    }
    $happy_lock_limited_task = array();
    foreach ($es_limited_task['aggregations']['all_ad_ids']['buckets'] as $ad_task) {
      $happy_lock_limited_task[$ad_task['key']] = $ad_task['sum_rmb']['value'];
    }

    $ad_ids = array_unique(array_merge(
      array_keys((array)$transfer_res),
      array_keys((array)$click),
      array_keys((array)$task),
      array_keys((array)$limited_task),
      array_keys((array)$install),
      array_keys((array)$native),
      array_keys((array)$native_transfer),
      array_keys((array)$callback)
    ));
    foreach ($ad_ids as $ad_id) {
      $stat = array(
        'id' => $ad_id,
        'rmb1' => round(
          (isset($transfer_res[$ad_id]) ? ($transfer_res[$ad_id]['rmb'] - $happy_lock_transfer[$ad_id]['rmb'] / 2) / 100 : 0) +
          ($task[$ad_id]['rmb'] + $limited_task[$ad_id]['rmb'] - $happy_lock_task[$ad_id] / 2 - $happy_lock_limited_task[$ad_id] / 2) / 100, 2),
        'device1' => (int)$transfer_res[$ad_id]['transfer'],
        'native' =>(int)$native[$ad_id],
        'native_transfer' => (int)$native_transfer[$ad_id],
        'task_num' => $task[$ad_id]['num'] + $limited_task[$ad_id]['num'],
        'task_rmb' => ($task[$ad_id]['rmb'] + $limited_task[$ad_id]['rmb'] - $happy_lock_task[$ad_id] / 2 - $happy_lock_limited_task[$ad_id] / 2) / 100,
        'task_ready' => $task[$ad_id]['ready'],
        'cpa' => $cpa[$ad_id],
        'click' => $click[$ad_id],
        'install' => $install[$ad_id],
        'callback' => $callback[$ad_id],
      );
      $value[$ad_id] = $stat;
    }
    return $value;
  }

  public function get_daily_ad_stat($id, $start, $end) {
    $ratios = $this->get_ratios($start, $end);

    $ad_service = new AD();
    $ad_info = $ad_service->get_ad_info_by_id($id);

    $ad_transfer_service = new ADTransferStat();
    $transfer = $ad_transfer_service->get_ad_transfer_stat_by_ad($id, $start, $end);
    $happy_lock_transfer = $ad_transfer_service->get_ad_transfer_by_user($id, $start, $end, HappyLockStat::HAPPY_LOCK_USER_IDS);
    $magic_transfer = $ad_transfer_service->get_ad_transfer_by_user($id, $start, $end, [HappyLockStat::MAGIC_USER_ID , HappyLockStat::PUBLISHER_USER_ID]);

    $task_stat_service = new TaskStat();
    $task = $task_stat_service->get_ad_task_outcome_by_date($start, $end, $id);
    $limited_task = $task_stat_service->get_ad_limit_task_outcome_by_date($start, $end, $id);

    $happy_lock_service = new HappyLockStat();
    $happy_lock_task = $happy_lock_service->get_ad_happy_lock_task_stat($id, $start, $end, TaskStat::TASK_ES_TYPE);
    $happy_lock_limited_task = $happy_lock_service->get_ad_happy_lock_task_stat($id, $start, $end, TaskStat::LIMITED_TASK_ES_TYPE);

    $quote_service = new QuoteStat();
    $quote = $quote_service->get_ad_quote($id, $start, $end);
    $cpc = [];
    if ($ad_info['cpc_cpa'] == 'cpc') {
      $ad_info['is_cpc'] = true;
      $res = $quote_service->getClickAdsByDate(array('ad_id' => $id), $start, $end);
      foreach ($res as $value) {
        $cpc[$value['date']] = $value['nums'];
      }
    }

    $share = new Share();
    $combo = $share->get_share_ad_combo_rmb($id, $start, $end);

    $activity_cut_service = new ActivityCut();
    $ad_cut = $activity_cut_service->get_activity_cut_out_by_ad($id, $start, $end, false);
    $activity_outcome = $activity_cut_service->get_activity_cut_out_by_ad($id, $start, $end, true);
    $activity_income = $activity_cut_service->get_activity_income_by_ad($id, $start, $end);

    $result = array();
    $total = array(
      'is_amount' => true,
      'transfer' => 0,
      'cpa' => 0,
      'ratio' => 0,
      'real' => 0,
      'out' => 0,
      'income' => 0,
      'activity_income' => 0,
      'activity_out' => 0,
      'profit' => 0,
      'cpc' => 0,
    );
    for ($stamp = strtotime($start); $stamp <= strtotime($end); $stamp += 86400) {
      $date = date("Y-m-d", $stamp);
      $month = date(BetterDate::FORMAT_MONTH, $stamp);
      $stat = array(
        'date' => $date,
        'transfer' => (int)$transfer[$date]['transfer_total'],
        'cpa' => (int)$quote[$date]['nums'],
        'quote_rmb' => isset($quote[$date]) ? $quote[$date]['quote_rmb'] : $ad_info['quote_rmb'],
        'ratio' => $transfer[$date]['transfer_total'] ? (int)$quote[$date]['nums'] / (int)$transfer[$date]['transfer_total'] * 100 : 0,
        'real' => $transfer[$date]['transfer_total'] ? (int)$quote[$date]['nums'] * (int)$quote[$date]['quote_rmb'] / (int)$transfer[$date]['transfer_total'] : 0,
        'out' => $transfer[$date]['rmb_total'] + $task[$date] + $limited_task[$date] - ($happy_lock_transfer[$date]['rmb'] + $happy_lock_task[$date] + $happy_lock_limited_task[$date]) / 2 - $magic_transfer[$date]['rmb'] + $combo[$date] / 2,
        'income' => (int)$quote[$date]['nums'] * (int)$quote[$date]['quote_rmb'],
        'cut' => (int)$ad_cut[$date],
        'activity_out' => $activity_outcome[$date],
        'activity_income' => $activity_income[$date],
        'cpc' => (int)$cpc[$date],
      );
      $stat['profit'] = ($stat['income'] - $stat['cut'] + $stat['activity_income']) * TAX_RATIO - ($stat['out'] + $stat['activity_out']) * $ratios[$month];
      $total['transfer'] += $stat['transfer'];
      $total['cpa'] += $stat['cpa'];
      $total['out'] += $stat['out'];
      $total['income'] += $stat['income'];
      $total['profit'] += $stat['profit'];
      $total['cut'] += $stat['cut'];
      $total['activity_income'] += $stat['activity_income'];
      $total['activity_out'] += $stat['activity_out'];
      $total['cpc'] += $stat['cpc'];
      $result[] = $stat;
    }
    $total['ratio'] = $total['transfer'] != 0 ? $total['cpa'] * 100 / $total['transfer'] : 0;
    $total['real'] = $total['transfer'] != 0 ? $total['income'] / $total['transfer'] : 0;
    $result[] = $total;

    $is_admin = $_SESSION['admin_role'] != Admin::SALE && $_SESSION['admin_role'] != Admin::SALE_MANAGER;
    if (!$is_admin) {
      $admin_service = new Admin();
      $ad_info = $admin_service->check_ad_info_for_sale($ad_info);
      $ad_info = Utils::array_pick($ad_info, Admin::$FIELD_AD_INFO_SALE);
    }
    $ad_info['is_admin'] = $is_admin;

    return [$result, $ad_info];
  }

  /**
   * @param string $start 开始日期
   * @param string $end 结束日期
   * @param array $filters
   * @param array|null $order
   * @param int $page
   * @param int $page_size
   * @param array|null $options
   *
   * @return array|mixed
   */
  public function get_data($start, $end, $filters = [], $order =null, $page = 0, $page_size = 0, $options = null) {
    $result = [];

    $start = $start instanceof BetterDate ? $start : new BetterDate($start);
    $end = $end instanceof BetterDate ? $end : new BetterDate($end);
    $is_same_month = $start->isSameMonth($end);
    do {
      $start_date = $start->format(BetterDate::FORMAT);
      $end_date = $start->get_last_of_date_month($end);
      $result[] = $this->get_data_by_month($start_date, $end_date, [
        'is_same_month' => $is_same_month,
        'filters' => $filters,
        'order' => $order,
        'page' => $page,
        'page_size' => $page_size
      ]);
      $start->modify('first day of next month');
    }
    while ($start->isBefore($end));

    if ($is_same_month) {
      $result = $result[0];
      if ($page_size > 0) {
        $result[1] = array_slice($result[1], $page * $page_size, $page_size);
      }
      return $result;
    }

    if (!$options['no_merge']) {
      $result = $this->merge($result);
      if ($order) {
        $result[1] = Utils::array_sort($result[1], $order);
      }
      if ($page_size > 0) {
        $result[1] = array_slice($result[1], $page * $page_size, $page_size);
      }
    }

    return $result;
  }

  public function get_month_amount($start, $end, $need_amount = true) {
    $today = new BetterDate();
    $start = new BetterDate($start);
    $start->modify('first day of this month');
    $end = new BetterDate($end);
    $end->modify('last day of this month');
    $end = $today->isBefore($end) ? $today : $end;
    $redis = $this->get_redis();
    $result = [];
    do {
      $start_date = $start->format(BetterDate::FORMAT);
      $end_date = $start->get_last_of_date_month($end);
      $redis_key = $this->generate_cache_key($start_date, $end_date);
      $month = $start->format(BetterDate::FORMAT_MONTH);
      $result[$month] = json_decode($redis->get($redis_key), true);
      $start->modify('first day of next month');
    } while ($start->isBefore($end));
    if ($need_amount) {
      $result = $this->merge_total($result);
      $result = $this->amount_total($result);
    }
    return $result;
  }

  public function get_ratios($start, $end) {
    $totals = $this->get_month_amount($start, $end, false);
    $totals = array_map(function ($total) {
      return $total['ratio'];
    }, $totals);
    return $totals;
  }

  /**
   * 合并所有按月查询的结果
   * 因为一定会强制按月查询,所以必须合并
   * 合并的时候,先依据广告id,再依据 `owner` 合并
   *
   * @param array $result
   *
   * @return array
   */
  public function merge( $result ) {
    if (count($result) == 1) {
      unset($result['android']['ad_ids']);
      unset($result['ios']['ad_ids']);
      unset($result['ka']['ad_ids']);
      return $result[0];
    }
    // 先算出总计的平均成本
    $total = $this->merge_total(array_column($result, 0));
    $total = $this->amount_total($total, true);
    $amount = $this->merge_total(array_column($result, 2));
    $amount['ratio'] = $total['is_after_2016'] ? 1 : $total['ratio'];
    $amount = $this->amount_total($amount, true);

    $list = $this->merge_list(array_column($result, 1), $total);
    return [$total, $list, $amount];
  }

  public function judge_date($date, $end) {
    if (substr($date, 8, 2) == '01' &&
      date('Y-m-d', mktime(0, 0, 0, substr($date, 5, 2) + 1, 0, substr($date, 0, 4))) <= $end &&
      mktime(0, 0, 0, substr($date, 5, 2) + 1, 0, substr($date, 0, 4))< time() - 3600 * 30) {
      $monthly = true;
      $ymd = substr($date, 0, 7);
    } else {
      $monthly = false;
      $ymd = $date;
    }
    if ($monthly) {
      $date = date('Y-m-d', mktime(0, 0, 0, substr($date, 5, 2) + 1, 1, substr($date, 0, 4)));
    } else {
      $date = date('Y-m-d', mktime(0, 0, 0, substr($date, 5, 2), substr($date, 8, 2) + 1, substr($date, 0, 4)));
    }
    return array($ymd, $monthly, $date);
  }

  protected function amount( $list, $curr = []) {
    $keys = array_keys(self::$defaults);
    array_pop($keys);
    $total = array_merge([
      'android' => self::$defaults,
      'ios' => self::$defaults,
      'vip' => self::$defaults,
    ], self::$defaults, $curr);
    $platforms = [
      ADModel::ANDROID => 'android',
      ADModel::IOS => 'ios',
    ];
    foreach ( $list as $ad ) {
      $ad_app_type = $platforms[$ad['ad_app_type']];
      // 剩下的记总数
      foreach ( $keys as $key ) {
        $total[$key] += $ad[$key];
        $total[$ad_app_type][$key] += $ad[$key];
        if ($ad['is_vip'] == 1) {
          $total['vip'][$key] += $ad[$key];
        }
      }
      $total[$ad_app_type]['ad_ids'][] = $ad['ad_id'];
      if ($ad['is_vip'] == 1) {
        $total['vip']['ad_ids'][] = $ad['ad_id'];
      }
    }
    return $this->amount_total($total);
  }

  protected function amount_ad( $ad, $total ) {
    $ratio = $total['is_after_2016'] ? 1 : $total['ratio'];
    $ad = array_merge($ad, [
      // 计算实际价格
      'real_price'   => $ad['transfer'] ? round( $ad['income'] / $ad['transfer'] ) : 0,
      'profit'       => round( $ad['income'] - $ad['out'] * $ratio ),
      'profit_ratio' => $ad['income'] ? round( ( 1 - $ad['out'] * $ratio / $ad['income'] ) * 10000 ) : 0,
      'ratio'        => $ad['transfer'] ? round( $ad['cpa'] / $ad['transfer'] * 10000 ) : 0,
      'other_income' => $ad['out'] ? $ad['income'] - $ad['income'] * $ad['happy_lock_rmb'] / $ad['out'] : 0,
    ]);
    $ad['beprice'] = $this->get_beprice( $ad, $total );
    return $ad;
  }

  /**
   * 计算总和中各种需要合计的部分
   * 2016年之后,红包的运营成本不再计入总的运营成本,并且运营成本不再分摊到所有广告产品之中
   *
   * @param $total
   * @param bool $remove_ad_ids
   *
   * @return array
   */
  protected function amount_total( $total, $remove_ad_ids = false ) {
    if (!$total) {
      return null;
    }
    $ratio = $operation_cost = $total_cost = $transferCostRatio = $happyLockOperationCostRatio = 0;
    $is_after_2016 = $total['is_after_2016'];
    if (array_key_exists('happy_lock_total_cost', $total)) { // 只有全部数据才有这个值
      if ($is_after_2016) {
        $operation_cost = $total['transfer_cost'];
      } else {
        $operation_cost = $total['happy_lock_total_cost'] - $total['happy_lock_rmb'] + $total['transfer_cost'];
        $happyLockOperationCostRatio = ($total['happy_lock_total_cost'] - $total['happy_lock_rmb']) / $total['happy_lock_rmb'];
      }
      $total_cost          = $total['out'] + $operation_cost;
      $ratio               = $total['out'] > 0 ? $total_cost / $total['out'] : 0;
      $transferCostRatio = $total['transfer_cost'] / ($total['out'] - $total['happy_lock_rmb']);
    } else if ($total['ratio']) {
      $ratio = $is_after_2016 ? 1 : $total['ratio'];
      $transferCostRatio = $total['transferCostRatio'];
      $happyLockOperationCostRatio = $total['happyLockOperationCostRatio'];
    }
    list($total, $android, $ios, $vip) = array_map(function ($total) use ($ratio, $operation_cost, $total_cost, $is_after_2016, $transferCostRatio, $happyLockOperationCostRatio) {
      // 单平台与全平台"总支出"计算方式不同
      $is_single_platform = !array_key_exists('android', (array)$total);
      if ($is_single_platform) {
        $total['be_average_rmb'] = $total['transfer'] ? $total['out'] / $total['transfer'] : 0;
      } elseif ($ratio) {
        $total['ratio'] = $ratio;
        $total['transferCostRatio'] = $transferCostRatio;
        $total['happyLockOperationCostRatio'] = $happyLockOperationCostRatio;
      }
      $total['happy_lock'] = [
        'transfer'       => $total['happy_lock_transfer'],
        'out'            => $total['happy_lock_rmb'],
        'income' => $total['happy_lock_income'],
        'operation_cost' => $happyLockOperationCostRatio * $total['happy_lock_rmb'],
        'total_cost'     => $total['happy_lock_rmb'] + $happyLockOperationCostRatio * $total['happy_lock_rmb'],
        'average_rmb'    => $total['happy_lock_transfer'] ? ($is_after_2016 ? $total['happy_lock_rmb'] : $total['happy_lock_total_cost']) / $total['happy_lock_transfer'] : 0,
      ];
      $other_transfer = $total['transfer'] - $total['happy_lock_transfer'];
      $total['without_happy_lock'] = [
        'transfer'       => $total['transfer'] - $total['happy_lock_transfer'],
        'out'            => $total['out'] - $total['happy_lock_rmb'],
        'income' => $total['income'] - $total['happy_lock_income'],
        'operation_cost' => ($total['out'] - $total['happy_lock_rmb']) * $transferCostRatio,
        'total_cost'     => ($total['out'] - $total['happy_lock_rmb']) * (1 + $transferCostRatio),
        'average_rmb'    => $other_transfer ? ( $total['out'] - $total['happy_lock_rmb'] + $total['transfer_cost'] ) / $other_transfer : 0,
      ];
      if (!$total_cost || $is_single_platform) {
        if ($is_after_2016) {
          $operation_cost = $total['happy_lock']['operation_cost'] + $total['without_happy_lock']['operation_cost'];
          $total_cost = $total['out'] + $operation_cost;
        } else {
          $total_cost = ($ratio ? $ratio : $total['ratio']) * $total['out'];
          $operation_cost = $total_cost - $total['out'];
        }
      }
      $average_rmb         = $total['transfer'] ? $total_cost / $total['transfer'] : 0;
      $income              = $total['income'];
      $profit              = $income - $total_cost;
      $profit_ratio        = $income ? round($profit / $income * 10000) : 0;
      $total               = array_merge($total, [
        'transfer_ratio' => $total['transfer'] ? round($total['cpa'] / $total['transfer'] * 10000) : 0,
        'cpc_ratio' => $total['click'] ? round($total['cpc'] / $total['click'] * 10000) : 0,
        'operation_cost' => $operation_cost,
        'total_cost'     => $total_cost,
        'income'         => $income,
        'profit'         => $profit,
        'profit_ratio' => $profit_ratio,
        'average_rmb' => $average_rmb,
      ]);
      $total['count'] = is_array($total['ad_ids']) ? count(array_unique($total['ad_ids'])) : 0;

      return $total;
    }, [ $total, $total['android'], $total['ios'], $total['vip']]);

    if ($remove_ad_ids) {
      unset($android['ad_ids']);
      unset($ios['ad_ids']);
    }
    $total['android'] = $android;
    $total['ios'] = $ios;
    $total['vip'] = $vip;
    $total['count'] = $android['count'] + $ios['count'];
    return $total;
  }

  /**
   * 检查表是否存在
   *
   * @param $start
   * @param $end
   *
   * @return string
   */
  protected function check_cache_exist( $start, $end ) {
    $redis = $this->get_redis();
    $key = $this->generate_cache_key($start, $end);
    return $redis->exists($key);
  }

  protected function format_rmb_range( $rmb ) {
    if (is_array($rmb)) {
      return $rmb;
    }
    if (strpos($rmb, ',') === false) {
      return ['only' => $rmb, 'min' => $rmb, 'max' => $rmb];
    }
    $rmb = $rmb ? explode(',', $rmb) : ['0', '0'];
    $rmb = array_combine(['min', 'max'], $rmb);
    if ($rmb['min'] == $rmb['max']) {
      $rmb['only'] = $rmb['min'];
    }
    return $rmb;
  }

  /**
   * 生成缓存表的名称
   *
   * @param string $start 开始日期
   * @param string $end 结束日期
   *
   * @return string
   */
  protected function generate_cache_table_name( $start, $end ) {
    return "s_daily_stat_{$start}_{$end}" . (DEBUG ? '_debug' : '');
  }

  /**
   * 生成总和 Redis 缓存的 key
   *
   * @param $start
   * @param $end
   *
   * @return string
   */
  protected function generate_cache_key( $start, $end ) {
    return "daily_stat_{$start}_{$end}" . (DEBUG ? '_debug' : '');
  }

  protected function get_ad_info( $ad_ids, $filters ) {
    list($conditions, $params) = $this->parse_filter($ad_ids);
    if ($filters) {
      unset($filters['is_pub']); // 是否有外放不在这里判断
      list($filters, $filter_params) = $this->parse_filter($filters);
      $filters = ",($filters) AS `is_ok`";
      $params = array_merge($filter_params, $params);
    } else {
      $filters = '';
    }
    $sql = "SELECT b.`id`,b.`ad_name`,c.`agreement_id`,b.`create_time`,b.`ad_type`,`cid`,b.`status`,
              b.`pack_name`,b.`ad_app_type`,`ad_sdk_type`,`cpc_cpa`,`feedback`,
              `quote_rmb`,`seq_rmb`,`step_rmb`,c.`owner`,`execute_owner`,f.`vip_sales`,f.`is_vip`,
              d.`agreement_id` AS `aid`,`others`,
              COALESCE(NULLIF(`company_type`,0),e.`type`,f.`type`) AS `channel_type`,
              COALESCE(NULLIF(`company`,''),e.`full_name`,f.`full_name`) AS `full_name`,
              COALESCE(NULLIF(`company_short`,''),e.`alias`,f.`alias`,`channel`) AS `channel`
              $filters 
            FROM `t_adinfo` b
              JOIN `t_ad_source` c ON b.`id`=c.`id`
              LEFT JOIN `t_agreement` d ON c.`agreement_id`=d.`id`
              LEFT JOIN `t_channel_map` e ON c.`channel`=e.`id`
              LEFT JOIN `t_channel_map` f ON d.`channel_id`=f.`id`
            WHERE $conditions";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $state->execute($params);
    $result = $state->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
    if (is_array($result)) {
      $result = array_map(function ($ad) {
        $ad['is_vip'] = (int)$ad['is_vip'];
        $ad['others_info'] = preg_split('/(?:[\n\r]+)?={2,}(?:[\n\r]+)?/', $ad['others'])[1];
        return $ad;
      }, $result);
    }
    return $result;
  }

  protected function get_beprice( $ad, $total ) {
    $base = $total[$ad['ad_app_type'] == ADModel::ANDROID ? 'android' : 'ios']['be_average_rmb'];
    return round($ad['real_price'] - $base);
  }

  protected function get_data_by_month( $start, $end, $options ) {
    $table = $this->check_cache_exist( $start, $end );

    if ($table) {
      return $this->read_data_from_cache($start, $end, $options);
    }

    return $this->read_data_from_db($start, $end, true, $options);
  }

  /**
   * @return PDO
   */
  protected function get_daily_pdo() {
    $this->DB_daily = $this->DB_daily ? $this->DB_daily : require PROJECT_DIR . '/app/connector/pdo_daily.php';
    return $this->DB_daily;
  }

  protected function max_and_min( $a, $b ) {
    $a['max'] = max($a['max'], $b['max']);
    $a['min'] = min($a['min'], $b['min']);
    return $a;
  }

  /**
   * 合并单条广告的数据
   * 先合并可以累加的字段
   * 然后重新计算利润率等不能累加的字段
   *
   * @param array $lists 广告数据列表
   * @param array $total 总和数据
   *
   * @return array
   */
  protected function merge_list( $lists, $total ) {
    if (count($lists) == 1) {
      return $lists[0];
    }

    $first = array_shift($lists);
    $list =  array_reduce($lists, function ($result, $list) use ($total) {
      foreach ( $list as $composite_key => $ad ) {
        if (!array_key_exists($composite_key, $result)) {
          $result[$composite_key] = $ad;
          continue;
        }

        $record = $result[$composite_key];
        foreach ( self::STAT_MERGE_KEYS as $key ) {
          $record[$key] += $ad[$key];
        }
        // 合并单价和分数
        $record['quote_rmb'] = $this->max_and_min($ad['quote_rmb'], $record['quote_rmb']);
        $record['step_rmb'] = $this->max_and_min($ad['step_rmb'], $record['step_rmb']);
        $result[$composite_key] = $record;
      }

      return $result;
    }, $first);

    // 计算不能累加的部分
    return array_map(function ($ad) use($total) {
      return $this->amount_ad($ad, $total);
    }, $list);
  }

  protected function merge_total( $totals ) {
    $totals = array_filter($totals);
    if (!$totals) {
      return null;
    }
    if (count($totals) == 1) {
      return array_pop($totals);
    }
    $first = array_shift($totals);
    $keys = array_filter(array_keys( $first ));
    $platform_keys = array_keys($first['android']);
    $total = array_reduce($totals, function ($memo, $total) use ($keys, $platform_keys) {
      $merged = array_map(function ($key) use ($total, $memo, $keys, $platform_keys) {
        if ($key == 'created_time') { // 创建时间,取最早的一个吧,因为如果数据对不上多半是早的那个有问题
          return min($total[$key], $memo[$key]);
        }
        if ($key == 'is_after_2016') {
          return $total[$key] || $memo[$key];
        }
        if (in_array($key, ['android', 'ios', 'vip'])) {
          if (!array_key_exists($key, $total) || !array_key_exists($key, $memo)) {
            return array_key_exists($key, $total) ? $total[$key] : $memo[$key];
          }
          return @array_combine($platform_keys, array_map(function ($a, $b) {
            if (is_array($a)) {
              return array_unique(array_merge($a, (array)$b));
            }
            return array_sum(func_get_args());
          }, $total[$key], $memo[$key]));
        }
        if (in_array($key, ['happy_lock', 'without_happy_lock', 'db', 'log', 'ad_ids'])) {
          return null;
        }
        return $total[$key] + $memo[$key];
      }, $keys );
      return array_combine($keys, $merged);
    }, $first);
    return $total;
  }

  protected function parse_filter( array $filters = null, array $options = [ 'to_string' => true ] ) {
    if (!$filters) {
      return ['', null];
    }
    $filters = $this->move_field_to($filters, 'ad_name', 'b');
    $filters = $this->move_field_to( $filters, 'ad_app_type', 'b' );
    $filters = $this->move_field_to($filters, 'owner', 'c');
    $filters = $this->move_field_to($filters, 'agreement_id', 'c');
    $filters = $this->move_field_to($filters, 'is_vip', 'f');
    $filters = $this->move_field_to( $filters, 'vip_sales', 'f' );
    if ($filters['ad_sdk_type'] == 'cpc') {
      $filters['ad_sdk_type'] = 1;
      $filters['cpc_cpa'] = 'cpc';
    }
    $spec = ['keyword', 'channel', 'follow', 'salesman', 'is_pub'];
    $picks = Utils::array_pick($filters, $spec);
    $omits = Utils::array_omit($filters, $spec);
    list($conditions, $params) = parent::parse_filter( $omits, ['to_string' => false] );
    foreach ( $picks as $key => $value ) {
      switch ($key) {
        case 'keyword':
          $conditions[] = ' (b.`ad_name` LIKE :keyword OR COALESCE(NULLIF(d.`company_short`,\'\'),e.`alias`,f.`alias`,c.`channel`) LIKE :keyword)';
          $params[':keyword'] = '%' . $value . '%';
          break;

        case 'channel':
          $conditions[] = ' (`company_short`=:channel OR e.`alias`=:channel OR f.`alias`=:channel OR `channel`=:channel)';
          $params[':channel'] = $value;
          break;

        case 'follow': // 跟包商务,不包含既是负责人又是执行人的情况
          $conditions[] = ' c.`owner`!=c.`execute_owner`';
          break;

        case 'salesman':
          $conditions[] = ' (c.`owner`=:salesman OR c.`execute_owner`=:salesman OR f.`vip_sales`=:salesman)';
          $params[':salesman'] = $value;
          break;

        case 'is_pub':
          if ($value !== null) {
            $conditions[] = $value ? '`publisher_income`>0' : '(`transfer`>0 OR `task_out`>0)';
          }
          break;
      }
    }
    if ($options['to_string']) {
      $conditions = $conditions ? implode(' AND ', $conditions) : '1';
    }
    if (!is_array($conditions) && $conditions && $options['is_append']) {
      $conditions = ' AND ' . $conditions;
    }
    return [ $conditions, $params ];
  }

  /**
   * 从缓存中读取数据
   *
   * @param $start
   * @param $end
   * @param array $options
   *    ['filters'] array 筛选条件
   *    ['order'] array 排序方式
   *    ['page'] int 页码
   *    ['page_size'] int 条数
   *    ['is_same_month'] bool 是否来自同一个月,只有来自同一个月才能应用order和分页
   *    ['need_amount'] bool 是否需要合并被筛选的项,默认为 true
   *
   * @return array
   * @throws Exception
   */
  protected function read_data_from_cache( $start, $end, $options ) {
    $filters = $options['filters'];
    $is_same_month = $options['is_same_month'];
    $order = $options['order'];
    $page = $options['page'];
    $page_size = $options['page_size'];
    $need_amount = isset($options['need_amount']) ? $options['need_amount'] : true;
    $is_after_2016 = $start >= '2016-01-01';

    // 先检查缓存是否完成,不然就等待5s,然后再检查,1分钟后报错
    $redis = $this->get_redis();
    $key = $this->generate_cache_key($start, $end);

    $DB = $this->get_daily_pdo();
    $filters = $this->move_field_to($filters, 'owner', 'a');
    list($conditions, $params) = $this->parse_filter($filters);
    $conditions = $conditions ? $conditions : '1';
    $order = $is_same_month && $order ? 'ORDER BY ' . $this->get_order($order) : '';
    // 这里如果有筛选条件,就不能有 limit
    $limit = $filters || !$is_same_month || $page_size == 0 ? '' : "LIMIT $page,$page_size";
    $table = $this->generate_cache_table_name($start, $end);

    $sql = "SELECT a.*,b.`ad_name`,c.`agreement_id`,`execute_owner`,`ad_sdk_type`,
              b.`create_time`,`seq_rmb`,b.`ad_type`,b.`ad_app_type`,`cid`,b.`status`,f.`is_vip`,
              f.`vip_sales`,d.`agreement_id` AS `aid`,`others`,`feedback`,
              COALESCE(NULLIF(`company`,''),e.`full_name`,f.`full_name`) AS `full_name`,
              COALESCE(NULLIF(`company_short`,''),e.`alias`,f.`alias`,`channel`) AS `channel`,
              COALESCE(NULLIF(d.`company_type`,0),e.`type`,f.type) AS `channel_type`,
              IF(a.`quote_rmb`='', b.`quote_rmb`, a.`quote_rmb`) AS `quote_rmb`,`cpc_cpa`
            FROM `$table` a
              LEFT JOIN `t_adinfo` b ON a.`ad_id`=b.`id`
              JOIN `t_ad_source` c ON a.`ad_id`=c.`id`
              LEFT JOIN `t_agreement` d ON c.`agreement_id`=d.`id`
              LEFT JOIN `t_channel_map` e ON c.`channel`=e.`id`
              LEFT JOIN `t_channel_map` f ON d.`channel_id`=f.`id`
            WHERE $conditions
            $order
            $limit";
    $state = $DB->prepare($sql);
    $state->execute($params);
    $list = $state->fetchAll(PDO::FETCH_ASSOC);
    // 展开单价和得分
    $result = [];
    foreach ( $list as $ad ) {
      $ad['quote_rmb'] = $this->format_rmb_range($ad['quote_rmb']);
      $ad['step_rmb'] = $this->format_rmb_range($ad['step_rmb']);
      $ad['cpc_quote'] = $this->format_rmb_range($ad['cpc_quote']);
      $ad['ratio'] = $ad['transfer'] ? $ad['cpa'] / $ad['transfer'] * 10000 : 0;
      $ad['cut'] = (int)$ad['cut'];
      $ad['is_vip'] = (int)$ad['is_vip'];
      $ad['publisher_transfer'] = (int)$ad['publisher_transfer'];
      $ad['others_info'] = preg_split('/(?:[\n\r]+)?={2,}(?:[\n\r]+)?/', $ad['others'])[1];
      $result[$ad['ad_id'] . '_' . $ad['owner']] = $ad;
    }

    $total = json_decode($redis->get($key), true);
    $amount = $filters && $need_amount ? $this->amount($result, [
      'ratio' => $total['ratio'],
      'is_after_2016' => $is_after_2016,
    ]) : null;

    return [$total, $result, $amount, true];
  }

  /**
   * 从数据库里读取数据,只读取一段时间内的全部数据,然后放在缓存里
   * 将过滤条件放在 ad_info 里,最后输出的时候再过滤
   *
   * @param $start
   * @param $end
   * @param bool $cache_temp 是否记为临时数据
   * @param array $options 主要传递过滤条件
   *
   * @return array
   */
  protected function read_data_from_db( $start, $end, $cache_temp = false, $options = null ) {
    $redis = $this->get_redis();
    $redis_key = $this->generate_cache_key($start, $end);
    $table = $this->generate_cache_table_name( $start, $end );
    $mustache = new Mustache_Engine(['cache' => '/tmp']);
    $is_after_2016 = $start >= '2016-01-01'; // 2016 年后将不再计算红包锁屏的其它支出
    $filters = $options['filters'];
    $need_amount = isset($options['need_amount']) ? $options['need_amount'] : true;
    // 广告负责人、是否外放需要单独过滤
    $filter_owner = $filters['owner'] ? $filters['owner'] : null;
    $is_pub = $filters['is_pub'] ? $filters['is_pub'] : null;
    $filters = Utils::array_omit($filters, 'owner', 'is_pub');

    $list = $this->read_raw_data( $start, $end, $filters );

    // 红包锁屏实际打款
    $happy_lock_stat         = new HappyLockStat();
    $happy_lock_total_cost = $is_after_2016 ? 0 : (int)$happy_lock_stat->get_happy_lock_outcome($start, $end);

    // 优秀奖励推广奖励和一次性奖励
    $app_transfer = new AppTransferStat();
    $real_cost = $app_transfer->get_real_cost($start, $end);

    // 开发者奖励
    $reward = new Reward();
    $all_reward = $reward->get_sum_reward_by_date($start, $end);

    $total = $this->amount($list, [
      'happy_lock_total_cost' => $happy_lock_total_cost,
      'transfer_cost' => $real_cost + $all_reward,
      'is_after_2016' => $is_after_2016,
    ]);

    // 计算广告负责人变化
    $ad_service = new AD();
    list($owner_log, $new_owner) = $ad_service->get_ad_owner_operation_log($start, $end, $list);
    // 更新负责人
    foreach ( $new_owner as $ad_id => $owner ) {
      $list[$ad_id]['owner'] = $owner;
    }
    $omits = [];
    foreach ( $owner_log as $range => $ads ) {
      list($range_start, $range_end) = explode('_', $range);
      $ad_ids = array_keys($ads);
      $partition = $this->read_raw_data($range_start, $range_end, ['ad_id' => $ad_ids], $list);
      $partition = array_map(function ($ad, $ad_id) use ($ads) {
        $ad['owner'] = $ads[$ad_id];
        return $ad;
      }, $partition, array_keys($partition));
      $omits = array_merge($omits, $ad_ids);
      $list = array_merge($list, array_values($partition));
    }
    $list = Utils::array_omit($list, array_unique($omits));
    $list = array_map(function ($ad) use ($total) {
      return $this->amount_ad($ad, $total);
    }, $list);

    // 留下缓存,一份详细列表放在数据库,一份总计放在redis
    $sql = file_get_contents(dirname(__FILE__) . '/../../template/sql/create_daily_stat_cache_table.sql');
    $sql = $mustache->render($sql, [ 'table' => $table ]);
    $DB = $this->get_daily_pdo();
    $DB->exec("DROP TABLE IF EXISTS `$table`"); // 考虑到表结构目前还不稳定,所以先干掉之前的表
    $check = $DB->exec($sql);
    $log = $check !== false ? '创建缓存表成功' : '创建缓存表失败';
    $keys = array_keys($list);
    $list[array_pop($keys)]['last'] = true;
    $sql = file_get_contents(dirname(__FILE__) . '/../../template/sql/insert_daily_stat_cache.sql');
    $sql = $mustache->render($sql, [
      'table' => $table,
      'list' => array_values($list),
    ]);
    $check = $DB->exec($sql);
    $total['created_time'] = time();
    if ($cache_temp) {
      $redis->setex($redis_key, 86400, json_encode($total)); // 缓存1天,这个缓存不会被主动更新,所以不能缓存太久
    } else {
      $redis->set($redis_key, json_encode($total));
    }
    // 记录下来数据库操作信息
    $log .= $check ? '缓存完成' : '缓存失败';
    $total['log'] = $log;
    $total['db'] = $DB->errorInfo();

    // 只返回符合要求的数据
    $amount = null;
    if ($filters) {
      $list = array_filter($list, function ($ad) use ($filter_owner, $is_pub) {
        // 广告负责人
        $owner_ok = ! $filter_owner || $ad['owner'] == $filter_owner;
        // 外放渠道条件
        $pub_ok = $is_pub === null // 没有参数,全部
          || $is_pub && $ad['publisher_income'] > 0 // 外放或拼包
          || !$is_pub && ($ad['transfer'] > 0 || $ad['task_out'] > 0); // 点乐或拼包
        return $ad['is_ok'] == 1 && $owner_ok && $pub_ok;
      });
      if ($need_amount) {
        $amount = $this->amount($list, [
          'total' => $total,
          'is_after_2016' => $is_after_2016,
        ]);
      }
    }

    return [$total, $list, $amount];
  }

  /**
   * @param string $start 开始日期
   * @param string $end 结束日期
   * @param array $filters 筛选条件
   * @param array $all_ad_info optional 全部广告的属性信息,可以用来区分是否只取符合筛选条件的广告,默认为 null
   *
   * @return array
   */
  protected function read_raw_data( $start, $end, $filters, $all_ad_info = null ) {
    $all_filters = $all_ad_info ? $filters : null;

    // 登录广告主数据
    $quote_service = new QuoteStat();
    $quotes        = $quote_service->get_ad_income( $start, $end, $all_filters);
    $clicks        = $quote_service->get_cpc( $start, $end, $all_filters );
    $cpc           = $quote_service->get_click( $start, $end, $all_filters );

    $activity_cut_service = new ActivityCut();
    // 核减数据
    $ad_cut = $activity_cut_service->get_ads_activity_cut_out( $start, $end, false, $all_filters );
    $publisher_cut = $activity_cut_service->get_ads_activity_cut_out($start, $end, false, array_merge([
      'publisher_id' => [
        'operator' => 'IS NOT NULL'
      ]
    ], (array)$all_filters));
    // 活动成本
    $activity_outcome = $activity_cut_service->get_ads_activity_cut_out( $start, $end, true, $all_filters );
    // 活动收入
    $activity_income = $activity_cut_service->get_ads_activity_income( $start, $end, $all_filters );

    // 投放量
    $ad_transfer         = new ADTransferStat();
    $transfer            = $ad_transfer->get_ad_transfer_stat( $start, $end, $all_filters );
    $happy_lock_transfer = $ad_transfer->get_all_ad_transfer_by_user( $start, $end, HappyLockStat::HAPPY_LOCK_USER_IDS, $all_filters );
    $magic_transfer      = $ad_transfer->get_all_ad_transfer_by_user( $start, $end, [HappyLockStat::MAGIC_USER_ID, HappyLockStat::PUBLISHER_USER_ID], $all_filters );
    $quotedTransfer      = $ad_transfer->getQuotedTransfer($start, $end);

    // 红包锁屏
    $happy_lock_stat         = new HappyLockStat();
    $happy_lock_task         = $happy_lock_stat->get_all_ad_happy_lock_task( $start, $end, TaskStat::TASK_ES_TYPE );
    $happy_lock_limited_task = $happy_lock_stat->get_all_ad_happy_lock_task( $start, $end, TaskStat::LIMITED_TASK_ES_TYPE );

    // 外放渠道
    $publisher     = new PublisherStat();
    $publisher_out = $publisher->get_all_ad_stat( $start, $end, $all_filters );
    $publisher_income = $publisher->get_all_ad_income($start, $end, $all_filters);

    // 深度任务统计
    $task_stat    = new TaskStat();
    $task         = $task_stat->get_ad_task_stat_by_ad( $start, $end, $all_filters );
    $limited_task = $task_stat->get_ad_limited_task_stat_by_ad( $start, $end, $all_filters );

    // 分享广告
    $share = new Share();
    $combo = $share->get_share_combo_rmb_stat( $start, $end, $all_filters );

    // 全部广告的ID
    $ad_ids = array_unique( array_merge( array_keys( (array) $transfer ), array_keys( (array) $task ), array_keys( (array) $limited_task ), array_keys( $quotes ), array_keys( $cpc ), array_keys( $clicks ), array_keys($activity_outcome), array_keys((array)$publisher_out) ) );

    // 广告投放价格调整记录
    $rmb_change_service = new RmbChangeLog();
    $rmb_change         = $rmb_change_service->get_all_ad_rmb_change( $start, $end, $ad_ids );

    //取全部广告数据
    $info = $all_ad_info ? $all_ad_info : $this->get_ad_info( [ 'b.id' => $ad_ids ], $filters );

    $list = [ ];
    foreach ( $ad_ids as $ad_id ) {
      if ($cpc[$ad_id]) {
        $cpc_income = $cpc[$ad_id]['income'];
      } else {
        $cpc_income = (int) $info[ $ad_id ]['quote_rmb'] * (int) $clicks[ $ad_id ];
      }
      $rmb_key         = $rmb_change_service->generate_rmb_change_key( $info[ $ad_id ] );
      $ad              = array_merge( $info[ $ad_id ], [
        'ad_id'               => $ad_id,
        'rmb_out'             => isset( $transfer[ $ad_id ] ) ? $transfer[ $ad_id ]['rmb'] - $happy_lock_transfer[ $ad_id ]['rmb'] / 2 - $magic_transfer[ $ad_id ]['rmb'] : 0,
        'task_out'            => isset( $task[ $ad_id ] ) || isset( $limited_task[ $ad_id ] ) || isset( $combo[ $ad_id ] ) ? $task[ $ad_id ]['rmb'] + $limited_task[ $ad_id ]['rmb'] - $happy_lock_task[ $ad_id ] / 2 - $happy_lock_limited_task[ $ad_id ] / 2 + $combo[ $ad_id ] / 2 : 0,
        'transfer'            => (int) $transfer[ $ad_id ]['transfer'],
        'happy_lock_transfer' => (int) $happy_lock_transfer[ $ad_id ]['transfer'],
        'happy_lock_rmb'      => ( $happy_lock_transfer[ $ad_id ]['rmb'] + $happy_lock_task[ $ad_id ] + $happy_lock_limited_task[ $ad_id ] + $combo[ $ad_id ] ) / 2,
        'happy_lock_income'   => $cpc_income,
        'cpa'                 => (int) $quotes[ $ad_id ]['nums'],
        'income'              => $quotes[ $ad_id ]['income'] - $ad_cut[ $ad_id ] + $activity_income[ $ad_id ] + $cpc_income,
        'cut'                 => (int) $ad_cut[ $ad_id ],
        'activity_income'     => (int) $activity_income[ $ad_id ],
        'activity_out'        => (int) $activity_outcome[ $ad_id ],
        'publisher_out'       => (int) $publisher_out[ $ad_id ]['rmb'],
        'publisher_transfer' => (int)$publisher_out[$ad_id]['num'],
        'publisher_income' => (int)$publisher_income[$ad_id]['rmb'],
        'publisher_cpa' => (int)$publisher_income[$ad_id]['num'],
        'publisher_cut' => (int)$publisher_cut[$ad_id],
        'quoted_transfer' => (int)$quotedTransfer[$ad_id],
        'click' => (int)$clicks[$ad_id],
        'cpc' => $cpc[$ad_id] ? (int)$cpc[$ad_id]['nums'] : (int)$clicks[$ad_id],
        'cpc_income' => $cpc_income,
      ] );
      $ad['quote_rmb'] = isset( $quotes[ $ad_id ] ) ? $quotes[ $ad_id ] : $this->format_rmb_range( $ad['quote_rmb'] );
      $ad['cpc_quote'] = isset($cpc[$ad_id]) ? $cpc[$ad_id] : $ad['quote_rmb'];
      $ad['step_rmb']  = isset( $rmb_change[ $rmb_key ] ) ? $rmb_change[ $rmb_key ] : $this->format_rmb_range( $ad['step_rmb'] );
      $ad['out']       = $ad['rmb_out'] + $ad['task_out'] + $ad['activity_out'] + $ad['publisher_out'];
      if ( $ad['transfer'] && array_key_exists( $ad_id, $quotes ) ) {
        $ad['happy_lock_income'] += ( $quotes[ $ad_id ]['income'] - $ad_cut[ $ad_id ] + $activity_income[ $ad_id ] ) * $ad['happy_lock_transfer'] / $ad['transfer'];
      }
      $ad['happy_lock_cut'] = $ad['out'] ? $ad_cut[ $ad_id ] * $ad['happy_lock_rmb'] / $ad['out'] : $ad_cut[ $ad_id ];
      $ad['other_cut']      = $ad['out'] ? $ad_cut[ $ad_id ] * ( 1 - $ad['happy_lock_rmb'] / $ad['out'] ) : 0;

      $list[ $ad_id ] = $ad;
    }

    return $list;
  }
}