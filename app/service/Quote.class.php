<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 13-12-26
 * Time: 下午4:24
 */

namespace diy\service;

use PDO;

class Quote extends Base {
  /**
   * 取一段时间的广告收入，并按时间和广告id合并数据
   * @see HistoryInfo
   *
   * @param array $ad_ids
   * @param String $start
   * @param String $end
   *
   * @return array
   */
  public function get_quote($ad_ids, $start, $end) {
    $DB = $this->get_read_pdo();
    list($conditions, $params) = $this->parse_filter(['ad_id' => $ad_ids], ['is_append' => true]);
    $sql = "SELECT `ad_id`, `quote_rmb`, `nums`, `quote_date`
            FROM `t_adquote`
            WHERE `quote_date`>=:start AND `quote_date`<=:end $conditions";
    $state = $DB->prepare($sql);
    $state->execute(array_merge([':start' => $start, ':end' => $end], $params));
    $quote = $state->fetchAll(PDO::FETCH_ASSOC);
    $result = array();
    foreach ($quote as $value) {
      $month = substr($value['quote_date'], 0, 7);
      $ad_id = $value['ad_id'];
      $income = $value['quote_rmb'] * $value['nums'];
      if (isset($result[$ad_id])) {
        $result[$ad_id][$month] += $income;
      } else {
        $result[$ad_id] = array(
          $month => $income,
        );
      }
    }
    return $result;
  }

  public function get_all_quote_ad($start, $end) {
    $DB = $this->get_read_pdo();
    $sql = "SELECT `ad_id`
            FROM `t_adquote`
            WHERE `quote_date`>=:start AND `quote_date`<=:end AND `nums`>0";
    $state = $DB->prepare($sql);
    $state->execute([':start' => $start, ':end' => $end]);
    return $state->fetchAll(PDO::FETCH_COLUMN);
  }

  /**
   * 取某时间段内某些广告的客户数据和单价，因为单价可变，所以这里进行预计算
   * 生成数组以ad_id为一级索引
   *
   * @param string $start
   * @param string $end
   * @param array $adids
   * @param bool $is_all
   *
   * @return array
   */
  public function get_by_ads($start, $end, $adids, $is_all = false) {
    $DB = $this->get_read_pdo();
    $conditions = '';
    $params = [];
    if (!$is_all) {
      list($conditions, $params) = $this->parse_filter(['ad_id' => $adids], ['is_append' => true]);
    }
    $sql = "SELECT ad_id, quote_rmb, SUM(nums) AS cpa
            FROM t_adquote
            WHERE quote_date>=:start AND quote_date<=:end $conditions
            GROUP BY ad_id, quote_rmb";
    $state = $DB->prepare($sql);
    $state->execute(array_merge([':start' => $start, ':end' => $end], $params));
    $quote = $state->fetchAll(PDO::FETCH_ASSOC);
    $result = array();
    foreach ($quote as $value) {
      $ad = isset($result[$value['ad_id']]) ? $result[$value['ad_id']] : array(
        'min' => $value['quote_rmb'],
        'max' => 0,
        'income' => 0,
        'cpa' => 0,
      );
      $ad['income'] += $value['quote_rmb'] * $value['cpa'];
      $ad['cpa'] += $value['cpa'];
      $ad['min'] = $ad['min'] > $value['quote_rmb'] ? $value['quote_rmb'] : $ad['min'];
      $ad['max'] = $ad['max'] < $value['quote_rmb'] ? $value['quote_rmb'] : $ad['max'];
      $result[$value['ad_id']] = $ad;
    }
    return $result;
  }

  public function get_ad_quote_by_owner($start, $end, $owner) {
    $DB = $this->get_read_pdo();

    $sql = "SELECT ad_id,sum(quote_rmb*nums) AS income,SUM(nums) AS cpa
            FROM t_adquote AS a JOIN t_ad_source AS b ON a.ad_id=b.id
            WHERE quote_date>=:start AND quote_date<=:end AND b.owner=:owner
            GROUP BY ad_id";
    $state = $DB->prepare($sql);
    $state->execute([':start' => $start, ':end' => $end, ':owner' => $owner]);
    return $state->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_UNIQUE|PDO::FETCH_GROUP);
  }
} 