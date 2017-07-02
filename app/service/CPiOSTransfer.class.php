<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 16/8/17
 * Time: 下午6:49
 */

namespace diy\service;


use diy\model\ADModel;
use diy\utils\Utils;
use PDO;

class CPiOSTransfer extends CPTransfer {
  protected $transferData;
  protected $todayTransfer;
  protected $callback;
  protected $todayCallback;

  public function fetch(  ) {
    $transfer = new Transfer();
    $transferStat = new TransferStat();
    $this->transferData  = $transfer->get_ad_transfer(['ad_id' => $this->ad_ids], 'ad_id');
    $this->todayTransfer = $transfer->get_ad_transfer([
      'ad_id' => $this->ad_ids,
      'transfer_date' => $this->today,
    ], 'ad_id');
    $this->callback      = $transferStat->get_income_stat_ios(['ad_id' => $this->ad_ids]);
    $this->todayCallback = $transferStat->get_income_stat_ios([
      'ad_id' => $this->ad_ids,
      'adnotify_date' => $this->today,
    ]);
  }

  public function fetchDashboardData() {
    $yesterday = date('Y-m-d', time() - 86400);
    $today = date('Y-m-d');
    $month_ago = date('Y-m-d', time() - 86400 * 30);
    $transfer = new Transfer();

    // 取昨日激活数
    $yesterday_transfer = $transfer->get_ad_transfer(array(
      'start' => $yesterday,
      'end' => $yesterday,
      'ad_id' => $this->ad_ids,
    ));

    // 取今天激活
    $today_transfer = $transfer->get_ad_transfer(array(
      'start' => $this->today,
      'end' => $this->today,
      'ad_id' => $this->ad_ids,
    ));

    // 取一个月内的流量统计
    $chart = $transfer->get_ad_transfer(array(
      'start' => $month_ago,
      'end' => $yesterday,
      'ad_id' => $this->ad_ids,
    ), 'transfer_date');
    $chart = $this->fillEmptyData($chart);

    return [$yesterday_transfer, $today_transfer, $chart];
  }

  public function getADStat( $start, $end ) {
    $line = '2016-04-25'; // 这之后的数据才是正确的
    $transfer_start = $transfer = $keyword = null;
    if ($start <= $line) {
      $transfer_start = $start;
      $start = '2016-04-26';
    }
    if ($end > $line) {
      $DB = $this->get_stat_pdo();
      $sql = "SELECT `notify_date`,`num`,`search_key`
            FROM `s_ios_adnotify_by_key`
            WHERE `ad_id`=:ad_id AND `notify_date`>=:start AND `notify_date`<=:end
            ORDER BY `notify_date` DESC ";
      $state = $DB->prepare( $sql );
      $state->execute([
        ':ad_id' => $this->ad_ids,
        ':start' => $start,
        ':end' => $end,
      ]);
      $keyword = $state->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_GROUP);
      if (!$transfer_start ) {
        return $this->outputStat($keyword);
      }
    }

    $DB = $this->get_read_pdo();
    $sql = "SELECT `transfer_date`,`transfer_total` AS `num`,'' AS `search_key`
            FROM `s_transfer_stat_ad`
            WHERE `ad_id`=:ad_id AND `transfer_date`>=:start AND `transfer_date`<=:end
            ORDER BY `transfer_date` DESC";
    $state = $DB->prepare($sql);
    $state->execute([
      ':ad_id' => $this->ad_ids,
      ':start' => $transfer_start,
      ':end' => $line,
    ]);
    $transfer = $state->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_GROUP);
    $transfer = array_merge($keyword, $transfer);

    return $this->outputStat( $transfer );
  }

  public function merge( array $ads, array $fields ) {
    return array_map(function ($ad, $ad_id) use ($fields) {
      $ad['id'] = $ad_id;
      // 按点乐结算,取有效推广数,也就是 `transfer`; API 接口,按回调数
      $is_api               = $ad['feedback'] == 4;
      $ad['transfer']       = (int)( $is_api ? $this->callback[$ad_id] : $this->transferData[$ad_id]);
      $ad['today_transfer'] = (int)($is_api ? $this->todayCallback[$ad_id] : $this->todayTransfer[$ad_id]);
      $ad['status'] = time() < strtotime($ad['start_time']) && $ad['status'] == ADModel::ONLINE ? 2 : (int)$ad['status']; // 在线,且当前时间<设定的投放时间 => 待投放
      $ad                   = Utils::array_pick($ad, $fields);
      return $ad;
    }, $ads, $this->ad_ids);
  }

  /**
   * @param $transfer
   *
   * @return array
   */
  private function outputStat( $transfer ) {
    return array_map( function ( $keywords, $date ) {
      $oneDay = [
        'date'     => $date,
        'keywords' => $keywords,
        'count'    => count( $keywords ),
        'total'    => array_sum( array_column( $keywords, 'num' ) ),
      ];

      return $oneDay;
    }, $transfer, array_keys( $transfer ) );
  }
}