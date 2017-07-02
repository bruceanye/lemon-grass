<?php
/**
 * Created by PhpStorm.
 * User: hemengyuan
 * Date: 15/5/28
 * Time: 上午11:53
 */

namespace diy\service;


use diy\model\PubModel;
use diy\service\Base;
use diy\utils\Utils;
use PDO;

class PublisherStat extends Base {
  public function get_stat($start, $end, $filters) {
    $publisher_sql = $owner_sql = '';
    if ($filters['publisher_name']) {
      $pub_service = new Pub();
      $ad_ids = $pub_service->get_ad_ids_by_publisher($filters['publisher_name']);
      $ad_ids_sql = implode("','", $ad_ids);
      $publisher_sql = " and ad_id in ('" . $ad_ids_sql . "')'";
      unset($filters['publisher_name']);
    }
    if (in_array($_SESSION['admin_role'], array(Admin::SALE, Admin::MEDIA))) {
      $owner_sql = ' and d.out_owner=' . $_SESSION['admin_id'];
    }

    $ad_service = new AD();
    $ads_info = $ad_service->get_all_pub_ads($filters);

    $sql = "select b.`ad_id`,a.`pub_id`,sum(`out_num`) as `out_num`,sum(`out_num`*a.`out_rmb`) as `outcome`,
      sum(`owner_num`) as `owner_num`,b.`out_rmb`,`publisher_name`,b.`status`,`name` as `out_owner_name`,
      sum(`owner_num`*f.`quote_rmb`) as `owner_income`
      from t_pub_log as a
        join t_pub_info as b on a.pub_id=b.id
        join t_agreement as c on b.out_agreement_id=c.id
        join t_publisher as d on c.channel_id=d.id
        join t_admin as e on d.out_owner=e.id
        left join t_adquote as f on (b.ad_id=f.ad_id and a.quote_date=f.quote_date)
      where a.quote_date>=:start and a.quote_date<=:end and (out_num>0 or owner_num>0)" . $publisher_sql . $owner_sql . "
      group by ad_id,pub_id";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $state->execute(array(':start' => $start, ':end' => $end));
    $stat = $state->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_GROUP);

    $quote_service = new QuoteStat();
    $ad_quote = $quote_service->get_ad_income($start, $end);

    $result = $total = array();
    $ad_ids = array_intersect(array_keys($ads_info), array_keys($stat));
    foreach ($ad_ids as $ad_id) {
      $owner_num = $owner_income = 0;
      foreach ($stat[$ad_id] as $value) {
        $owner_num += $value['owner_num'];
        $owner_income += $value['owner_income'];
        $total['out_num'] += $value['out_num'];
        $total['outcome'] += $value['outcome'];
      }
      $income = !isset($ads_info[$ad_id]['quote_rmb']) ? 0 : $ad_quote[$ad_id]['income'];
      $owner_income = !isset($ads_info[$ad_id]['quote_rmb']) ? 0 : $owner_income;
      $result[] = array_merge($ads_info[$ad_id], array(
        'ad_id' => $ad_id,
        'income' => $income,
        'owner_income' => $owner_income,
        'owner_num' => $owner_num,
        'pubs' => $stat[$ad_id],
        'count' => count($stat[$ad_id]),
      ));
      $total['income'] += $income;
      $total['owner_num'] += $owner_num;
      $total['owner_income'] += $owner_income;
    }

    return [$result, $total];
  }

  public function get_ad_stat($start, $end, $ad_id) {
    $owner_sql = '';
    if (in_array($_SESSION['admin_role'], array(Admin::SALE, Admin::MEDIA))) {
      $owner_sql = ' and d.out_owner=' . $_SESSION['admin_id'];
    }
    $sql = "select quote_date,d.id,a.out_rmb,out_num,a.out_rmb*out_num as outcome,owner_num,publisher_name
      from t_pub_log as a
      join t_pub_info as b on a.pub_id=b.id
      join t_agreement as c on b.out_agreement_id=c.id
      join t_publisher as d on c.channel_id=d.id
      where quote_date>=:start and quote_date<=:end and ad_id=:ad_id and (out_num>0 or owner_num>0) $owner_sql
      group by quote_date,d.id";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $state->execute(array(':start' => $start, ':end' => $end, ':ad_id' => $ad_id));
    $stat = $state->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_GROUP);

    $ad_transfer_service = new ADTransferStat();
    $ad_transfer = $ad_transfer_service->get_ad_transfer_stat_by_ad($ad_id, $start, $end);

    $quote_service = new QuoteStat();
    $ad_quote = $quote_service->get_ad_quote($ad_id, $start, $end);

    $result = array();
    for($date = $start; $date <= $end; ) {
      $outcome = $out_num = $owner_num = 0;
      if ($stat[$date]) {
        foreach ($stat[$date] as $value) {
          $outcome += $value['outcome'];
          $out_num += $value['out_num'];
          $owner_num += $value['owner_num'];
        }
      }
      $result[] = array(
        'date' => $date,
        'nums' => $ad_quote[$date]['nums'],
        'income' => $ad_quote[$date]['income'],
        'out_num' => $out_num,
        'outcome' => $outcome,
        'owner_num' => $owner_num,
        'pubs' => $stat[$date],
        'transfer' => $ad_transfer[$date]['transfer_total'],
        'count' => count($stat[$date]),
      );
      $date = date("Y-m-d", strtotime($date) + 86400);
    }

    return $result;
  }

  public function stat_total($stat, $key) {
    $total = array(
      'out_num' => 0,
      'outcome' => 0,
      'owner_num' => 0,
      'nums' => 0,
      'income' => 0,
    );

    foreach ($stat as $value) {
      foreach ($total as $k => $v) {
        $total[$k] += $value[$k];
      }
    }

    $total['is_amount'] = true;
    $total[$key] = 'amount';
    $total['ratio'] = $total['nums'] ? $total['out_num'] / $total['nums'] : 0;
    return $total;
  }

  public function get_publisher_stat_ymd($start, $end, $publisher_id) {
    $sql = "select quote_date,sum(out_num) as out_num,sum(a.out_rmb*out_num) as outcome,sum(owner_num) as owner_num
      from t_pub_log as a
      join t_pub_info as b on a.pub_id=b.id
      join t_agreement as c on b.out_agreement_id=c.id
      where quote_date>=:start and quote_date<=:end and channel_id=:publisher_id
      group by quote_date";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $state->execute(array(':start' => $start, ':end' => $end, ':publisher_id' => $publisher_id));
    $stat = $state->fetchAll(PDO::FETCH_ASSOC);

    $stat[] = $this->stat_total($stat, 'quote_date');
    return $stat;
  }

  public function get_ad_num_one_day($ad_id, $date) {
    $sql = "select sum(out_num)
      from t_pub_log as a
      join t_pub_info as b on a.pub_id=b.id
      where quote_date=:date and ad_id=:ad_id";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $state->execute(array(':date' => $date, ':ad_id' => $ad_id));
    return $state->fetch(PDO::FETCH_COLUMN);
  }

  public function get_pubs_income_month($start, $end, $pub_ids) {
    $filters['pub_id'] = $pub_ids;
    list($conditions, $params) = $this->parse_filter($filters, array('is_append' => true) );
    $params[':start'] = $start;
    $params[':end'] = $end;
    $sql = "select pub_id,left(quote_date,7) as `month`,sum(a.out_rmb*out_num) as income
      from t_pub_log as a
      join t_pub_info as b on a.pub_id=b.id
      where left(quote_date,7)>=:start and left(quote_date,7)<=:end $conditions
      group by pub_id,`month`";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $state->execute($params);
    $stat = $state->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_GROUP);

    $result = array();
    foreach ($stat as $pub_id => $value) {
      foreach ($value as $v) {
        $result[$pub_id][$v['month']] = $v['income'];
      }
    }

    return $result;
  }

  public function get_pubs_check_income_by_publisher($start, $end, $publisher_id) {
    $sql = "select `pub_id`,`pub_id`,d.`ad_name`,sum(a.`out_rmb`*`out_num`) as `outcome`,f.`id` as `publisher_id`,`publisher_name`
      from t_pub_log as a
      join t_pub_info as b on a.pub_id=b.id
      join t_agreement as c on b.out_agreement_id=c.id
      join t_adinfo as d on b.ad_id=d.id
      join t_pub_payment as e on (left(a.quote_date,7)=left(e.`month`,7) and a.pub_id=e.id)
      join t_publisher as f on c.channel_id = f.id
      where left(quote_date,7)>=:start and left(quote_date,7)<=:end and channel_id=:publisher_id
      GROUP BY `pub_id`";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $state->execute(array(':start' => $start, ':end' => $end, ':publisher_id' => $publisher_id));
    $publisher_checked_pubs = $state->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_UNIQUE);

    $sql = "select `pub_id` from t_publisher_apply_pub_month as a join t_publisher_apply as b on a.apply_id=b.id
      where a.`month`>=:start and a.`month`<=:end and b.publisher_id=:publisher_id";
    $state = $DB->prepare($sql);
    $state->execute(array(':start' => $start, ':end' => $end, ':publisher_id' => $publisher_id));
    $publisher_applied_pubs = $state->fetchAll(PDO::FETCH_COLUMN);

    foreach ($publisher_applied_pubs as $publisher_applied_pub) {
      unset($publisher_checked_pubs[$publisher_applied_pub]);
    }

    return array_values($publisher_checked_pubs);
  }

  public function get_all_ad_stat( $start, $end, $filters ) {
    list($conditions, $params) = $this->parse_filter($filters, ['is_append' => true]);
    $sql = "SELECT `ad_id`,SUM(a.`out_rmb`*a.`out_num`) AS `rmb`,SUM(a.`out_num`) AS `num`
            FROM `t_pub_log` a 
              LEFT JOIN `t_pub_info` b ON a.`pub_id`=b.`id`
            WHERE `quote_date`>=:start AND `quote_date`<=:end AND b.`status`!=:status $conditions
            GROUP BY `ad_id`";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $state->execute(array_merge($params, [
      ':start' => $start,
      ':end' => $end,
      ':status' => PubModel::DELETE
    ]));
    return $state->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_UNIQUE);
  }
  
  public function get_all_ad_income($start, $end, array $filters = null) {
    $filters = $this->move_field_to($filters, 'ad_id', 'b');
    list($conditions, $params) = $this->parse_filter($filters, ['is_append' => true]);
    $sql = "SELECT b.`ad_id`,SUM(a.`owner_num`*c.`quote_rmb`) AS `rmb`,SUM(`owner_num`) AS `num`
            FROM `t_pub_log` a
              JOIN `t_pub_info` b ON a.`pub_id`=b.`id`
              JOIN `t_adquote` c ON b.`ad_id`=c.`ad_id` AND a.`quote_date`=c.`quote_date`
            WHERE status!=:status AND a.`quote_date`>=:start AND a.`quote_date`<=:end $conditions
            GROUP BY b.`ad_id`";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $state->execute(array_merge($params, [
      ':start' => $start,
      ':end' => $end,
      ':status' => PubModel::DELETE,
    ]));
    return $state->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_UNIQUE);
  }
}