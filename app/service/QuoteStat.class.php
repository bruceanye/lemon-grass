<?php
/**
 * Created by PhpStorm.
 * User: chensheng
 * Date: 15/7/7
 * Time: 下午2:51
 */

namespace diy\service;

use diy\model\ADModel;
use diy\utils\Utils;
use PDO;

class QuoteStat extends Base {
  const T_AD_CLICK = 't_ad_click';

  public function get_ad_quote($id, $start, $end) {
    $filters = [
      'ad_id' => $id,
      'start' => $start,
      'end' => $end,
    ];
    list($conditions, $params) = $this->parse_filter($filters);
    $sql = "select quote_date,nums,quote_rmb,quote_time
            from t_adquote
            where {$conditions}";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $state->execute($params);
    return $state->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_UNIQUE);
  }

  public function get_ad_income($start, $end, $filters = []) {
    $join = '';
    $filters = $filters === null ? [] : $filters;
    if (array_key_exists('ad_app_type', $filters)) {
      $has_app_type = in_array($filters['ad_app_type'], array(ADModel::ANDROID, ADModel::IOS));
      if ($has_app_type) {
        $join = $has_app_type ? 'join t_adinfo as b on a.ad_id=b.id' : '';
      } else {
        unset($filters['ad_app_type']);
      }
    }
    $filters['start'] = $start;
    $filters['end'] = $end;
    list($conditions, $params) = $this->parse_filter($filters, ['is_append' => true]);
    $sql = "select `ad_id`,a.`quote_rmb`,sum(`nums`) as `nums`
            from `t_adquote` as a
              $join
            where `nums`>0 $conditions
            group by ad_id,a.quote_rmb";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $state->execute($params);
    $quote = $state->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_GROUP);

    $result = array();
    foreach ($quote as $ad_id => $value) {
      $ad = array(
        'min' => PHP_INT_MAX,
        'max' => 0,
        'income' => 0,
        'nums' => 0,
      );
      foreach ($value as $v) {
        $ad['income'] += $v['quote_rmb'] * $v['nums'];
        $ad['nums'] += $v['nums'];
        $ad['min'] = $ad['min'] > $v['quote_rmb'] ? $v['quote_rmb'] : $ad['min'];
        $ad['max'] = $ad['max'] < $v['quote_rmb'] ? $v['quote_rmb'] : $ad['max'];
        $ad['only'] = $ad['min'] == $ad['max'] ? $ad['min'] : null;
      }
      $result[$ad_id] = $ad;
    }
    return $result;
  }

  public function get_all_ad_income($start, $end) {
    $sql = "SELECT sum(`nums`) AS nums,sum(quote_rmb*nums) AS `income`
            FROM `t_adquote`
            WHERE `quote_date`>=:start AND `quote_date`<=:end";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $state->execute(array(':start' => $start, ':end' => $end));
    return $state->fetch(PDO::FETCH_ASSOC);
  }

  public function get_ad_income_by_month($start, $end) {
    $DB = $this->get_read_pdo();

    $sql = "SELECT `ad_id`,left(`quote_date`,7) AS `month`,sum(quote_rmb*nums) AS `income`
            FROM `t_adquote`
            WHERE `quote_date`>=:start AND `quote_date`<=:end AND `nums`>0
            GROUP BY `ad_id`,`month`";
    $state = $DB->prepare($sql);
    $params = array(
      ':start' => $start,
      ':end' => $end
    );
    $state->execute($params);
    $result = $state->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_GROUP);

    $income = array();
    foreach ($result as $id => $value) {
      foreach ($value as $v) {
        $income[$id][$v['month']] = $v['income'];
      }
    }
    return $income;
  }

  public function get_cpc( $start, $end, $filters = null ) {
    list($conditions, $params) = $this->parse_filter($filters, ['is_append' => true]);
    $sql = "SELECT `ad_id`,SUM(`pv`) AS `num` 
            FROM `s_cpc_ad_click_stat`
            WHERE `d`>=:start AND `d`<=:end $conditions
            GROUP BY `ad_id`";
    $DB = $this->get_stat_pdo();
    $state = $DB->prepare($sql);
    $state->execute(array_merge($params, [':start' => $start, ':end' => $end]));
    return $state->fetchAll(PDO::FETCH_KEY_PAIR);
  }

  public function get_cpc_by_date( $ad_id, $start, $end ) {
    $sql = "SELECT `d`,`num`
            FROM `s_cpc_notify_stat`
            WHERE `d`>=:start AND `d`<:end AND `ad_id`=:ad_id";
    $DB = $this->get_stat_pdo();
    $state = $DB->prepare($sql);
    $state->execute([':start' => $start, ':end' => $end, ':ad_id' => $ad_id]);
    return $state->fetchAll(PDO::FETCH_KEY_PAIR);
  }

  public function get_click($start, $end, $filters = null) {
    list($conditions, $params) = $this->parse_filter($filters, ['is_append' => true]);
    $DB = $this->get_read_pdo();

    $sql = "SELECT `ad_id`,SUM(`nums`) AS `nums`,`click_rmb`,`click_date`
            FROM `t_ad_click`
            WHERE `click_date`>=:start AND `click_date`<=:end $conditions
            GROUP BY `ad_id`,`click_rmb`,`click_date`";
    $state = $DB->prepare($sql);
    $params = array_merge($params, array(
        ':start' => $start,
        ':end' => $end)
    );
    $state->execute($params);
    $clickStat = $state->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_GROUP);

    $ad_service = new AD();
    $result = array();
    foreach ($clickStat as $ad_id => $value) {
      // 统计点乐统计
      $cpcs = $this->getAllCpcById($ad_id, $start, $end);
      $quote_rmb = $ad_service->get_ad_info_by_id($ad_id)['quote_rmb'];

      $ad = array(
        'min' => PHP_INT_MAX,
        'max' => 0,
        'income' => 0,
        'nums' => 0,
      );
      foreach ($value as $v) {
        $click_date = $v['click_date'];
        $nums = ($v['nums'] || (int)$v['nums'] == 0) ? $v['nums'] : $cpcs[$click_date];
        unset($cpcs[$click_date]);
        $ad['income'] += $v['click_rmb'] * $nums;
        $ad['nums'] += $nums;
        $ad['min'] = $ad['min'] > $v['click_rmb'] ? $v['click_rmb'] : $ad['min'];
        $ad['max'] = $ad['max'] < $v['click_rmb'] ? $v['click_rmb'] : $ad['max'];
      }

      // 添加点乐统计(剔除已经录过的cpc)
      $dianjoy_cpc_nums = 0;
      foreach ($cpcs as $cpc) {
        $dianjoy_cpc_nums += $cpc;
      }
      $ad['nums'] = $ad['nums'] + $dianjoy_cpc_nums;
      $ad['income'] = $ad['income'] + ($dianjoy_cpc_nums * $quote_rmb);

      $result[$ad_id] = $ad;
    }
    return $result;
  }

  public function getAllCpcById($id, $start, $end) {
    $DB = $this->get_stat_pdo();

    $sql = "SELECT `d`,`pv`
            FROM `s_cpc_ad_click_stat`
            WHERE `d`>=:start AND `d`<=:end AND `ad_id`=:ad_id
            GROUP BY `d`";
    $state = $DB->prepare($sql);
    $state->execute(array(
      ':start' => $start,
      ':end' => $end,
      ':ad_id' => $id
    ));
    return $state->fetchAll(PDO::FETCH_KEY_PAIR);
  }

  public function getClickAdsByDate($filters, $start, $end) {
    $adService = new AD();
    $ad_id = $filters['ad_id'];
    $adinfo = $adService->get_ad_info_by_id($ad_id);

    $DB = $this->get_read_pdo();
    list($conditions, $params) = $this->parse_filter(Utils::array_pick($filters, array('ad_id')));
    $conditions = $conditions ? $conditions . " AND `click_date`>='$start' AND `click_date`<='$end'" : "";
    $sql = "SELECT `click_date`,`nums`,`click_rmb` AS `quote_rmb`
            FROM " . self::T_AD_CLICK . "
            WHERE $conditions
            GROUP BY `click_date`";
    $state = $DB->prepare($sql);
    $state->execute($params);
    $clickStat = $state->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_UNIQUE);

    // 获取点乐统计cpc（统计多天）
    $cpcs = $this->getAllCpcById($ad_id, $start, $end);

    $quote_rmb = $adinfo['quote_rmb'];
    $result = array();
    for($stamp = strtotime($end); $stamp >= strtotime($start); $stamp -= 86400) {
      $date = date("Y-m-d", $stamp);
      $adClick = array(
        'date' => $date,
        'quote_rmb' => $clickStat[$date] ? (int)$clickStat[$date]['quote_rmb'] : $quote_rmb,
        'nums' => $clickStat[$date] ? (int)$clickStat[$date]['nums'] : $cpcs[$date], // 如果没有录数，采用系统默认的
        'cpc' => $cpcs[$date]
      );
      $result[] = $adClick;
    }
    $result = array_reverse($result);
    return $result;
  }

  protected function parse_filter( array $filters = null, array $options = [ ] ) {
    return parent::parse_filter($filters, array_merge([
      'spec' => ['start', 'end'],
    ], $options));
  }

  protected function parseSpecialFilter( $spec, $options = null ) {
    $conditions = $params = [];
    foreach ( $spec as $key => $value ) {
      if (!$value) {
        continue;
      }
      switch ($key) {
        case 'start':
          $conditions[] = '`quote_date`>=:start';
          $params[':start'] = $value;
          break;

        case 'end':
          $conditions[] = '`quote_date`<=:end';
          $params[':end'] = $value;
          break;
      }
    }
    return [$conditions, $params];
  }
}