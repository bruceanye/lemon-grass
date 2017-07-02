<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 16/8/17
 * Time: 下午6:49
 */

namespace diy\service;


use diy\utils\Utils;

class CPAndroidTransfer extends CPTransfer {
  protected $quotes;
  protected $today_quotes;

  public function fetch(  ) {
    $service = new QuoteStat();
    $this->quotes = $service->get_ad_income(null, null, [
      'ad_id' => $this->ad_ids,
    ]);
    $this->today_quotes = $service->get_ad_income($this->today, $this->today, [
      'ad_id' => $this->ad_ids,
    ]);
  }

  public function fetchDashboardData(  ) {
    $yesterday = date('Y-m-d', time() - 86400);
    $month_ago = date('Y-m-d', time() - 86400 * 30);
    $service = new QuoteStat();
    $chart = $service->get_ad_quote($this->ad_ids, $month_ago, $yesterday);
    $today = $service->get_ad_quote($this->ad_ids, $this->today, $this->today);
    $yesterday = $service->get_ad_quote($this->ad_ids, $yesterday, $yesterday);

    $today = $this->getQuoteNum($today);
    $yesterday = $this->getQuoteNum($yesterday);
    $chart = array_map(function ($quote) {
      return $quote['nums'];
    }, $chart);
    $chart = $this->fillEmptyData($chart);

    return [$yesterday, $today, $chart];
  }

  public function getADStat( $start, $end ) {
    $service = new QuoteStat();
    $quotes = $service->get_ad_quote($this->ad_ids, $start, $end);
    $quotes = array_map(function ($quote, $date) {
      $quote['date'] = $date;
      $quote['income'] = $quote['quote_rmb'] * $quote['nums'];
      return $quote;
    }, $quotes, array_keys($quotes));
    return $quotes;
  }

  public function merge( array $ads, array $fields ) {
    return array_map(function ($ad, $ad_id) use ($fields) {
      $ad['id'] = $ad_id;
      $ad = array_merge($ad, (array)$this->quotes[$ad_id]);
      $ad['today_transfer'] = $this->today_quotes[$ad_id]['nums'];
      $ad['transfer'] = $ad['nums'];
      $ad['status'] = (int)$ad['status'];
      $ad = Utils::array_pick($ad, $fields);
      return $ad;
    }, $ads, $this->ad_ids );
  }

  private function getQuoteNum( $today ) {
    $today = array_pop($today);
    return $today['nums'];
  }
}