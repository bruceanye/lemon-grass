<?php
/**
 * Created by PhpStorm.
 * User: hemengyuan
 * Date: 16/2/17
 * Time: 下午2:34
 */

namespace diy\service;


use diy\model\ActivityCutModel;
use Exception;
use PDO;
use SQLHelper;

class ActivityCut extends Base
{
  public function get_all_activity_cut($start, $end, $is_activity) {
    $field_sql = $is_activity ? ',`user_id`,`title`,`type`' : ',`cut_type`';
    $table = $is_activity ? 't_ad_activity' : 't_ad_cut';
    $sql = "select a.`id`,`ad_id`,`rmb`,`start`,`end`,`comment`,`NAME` AS `admin` $field_sql
            from $table a
              JOIN `t_admin` b ON a.`admin`=b.`id`
            where `start`<=:end and `end`>=:start and a.`status`=:status";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $state->execute( [
      ':start' => $start,
      ':end' => $end,
      ':status' => ActivityCutModel::STATUS_NORMAL,
    ] );
    $result = $state->fetchAll(PDO::FETCH_ASSOC);

    $ad_service = new AD();
    $ads = $ad_service->get_all_ad_info();

    $users = array();
    if ($is_activity) {
      $user_service = new User();
      $users = $user_service->get_users();
    }

    $result = array_map( function ($activity) use ($ads, $users, $is_activity) {
      $ad_ids = explode(',', $activity['ad_id']);
      foreach ($ad_ids as $ad_id) {
        $activity['ads'][] = array_merge((array)$ads[$ad_id], array('id' => $ad_id));
      }
      if ($is_activity) {
        $activity['user'] = $users[$activity['user_id']];
      }
      return $activity;
    }, $result);

    return $result;
  }

  public function get_activity_cut_out_by_ad($id, $start, $end, $is_activity) {
    $table = $is_activity ? 't_ad_activity' : 't_ad_cut';
    $detail_table = $is_activity ? 't_ad_activity_detail' : 't_ad_cut_detail';
    $sql = "select `date`,sum(b.`rmb`)
            from $table as a
              join $detail_table as b on a.id=b.source_id
            where `date`<=:end and `date`>=:start and a.`status`=" . ActivityCutModel::STATUS_NORMAL . " and b.ad_id=:ad_id
            group by `date`";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $state->execute( [
      ':start' => $start,
      ':end' => $end,
      ':ad_id' => $id,
    ] );
    return $state->fetchAll(PDO::FETCH_KEY_PAIR);
  }

  public function get_activity_income_by_ad($id, $start, $end) {
    $sql = "select `date`,sum(b.`rmb`)
            from t_ad_activity as a
              JOIN `t_ad_activity_detail` as b ON a.id=b.source_id
            where `date`<=:end and `date`>=:start and a.`status`=" . ActivityCutModel::STATUS_NORMAL . " and b.ad_id=:ad_id and a.`type`=" . ActivityCutModel::TYPE_CUSTOMER . "
            group by `date`";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $state->execute( [
      ':start' => $start,
      ':end' => $end,
      ':ad_id' => $id,
    ] );
    return $state->fetchAll(PDO::FETCH_KEY_PAIR);
  }

  public function get_ads_activity_cut_out($start, $end, $is_activity, $filters = []) {
    $filters['a.status'] = ActivityCutModel::STATUS_NORMAL;
    $filters = $this->move_field_to($filters, 'ad_id', 'b');
    list($conditions, $params) = $this->parse_filter($filters, ['is_append' => true]);
    $table = $is_activity ? 't_ad_activity' : 't_ad_cut';
    $detail_table = $is_activity ? 't_ad_activity_detail' : 't_ad_cut_detail';
    $sql = "select b.`ad_id`,sum(b.`rmb`)
            from $table as a
              join $detail_table as b on a.id=b.source_id
            where `date`<=:end and `date`>=:start $conditions
            group by b.`ad_id`";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $state->execute( array_merge($params, [
      ':start' => $start,
      ':end' => $end,
    ] ));
    return $state->fetchAll(PDO::FETCH_KEY_PAIR);
  }

  public function get_ads_activity_income($start, $end, $filters = []) {
    $filters['a.status'] = ActivityCutModel::STATUS_NORMAL;
    $filters['a.type'] = ActivityCutModel::TYPE_CUSTOMER;
    $filters = $this->move_field_to($filters, 'ad_id', 'b');
    list($conditions, $params) = $this->parse_filter($filters, ['is_append' => true]);
    $sql = "select b.`ad_id`,sum(b.`rmb`)
            from t_ad_activity as a
              JOIN `t_ad_activity_detail` as b ON a.id=b.source_id
            where `date`<=:end and `date`>=:start $conditions
            group by b.`ad_id`";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $state->execute( array_merge($params, [
      ':start' => $start,
      ':end' => $end,
    ] ));
    return $state->fetchAll(PDO::FETCH_KEY_PAIR);
  }

  public function add_activity_cut_detail($param, $is_activity) {
    $sql = "select sum(transfer_total)
            from s_transfer_stat_ad
            where `transfer_date`<=:end and `transfer_date`>=:start and ad_id in (:ad_id)";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $state->execute( [
      ':start' => $param['start'],
      ':end' => $param['end'],
      ':ad_id' => $param['ad_id'],
    ] );
    $transfer_total = $state->fetch(PDO::FETCH_COLUMN);

    $sql = "select `ad_id`,`transfer_date` as `date`,`transfer_total`
            from s_transfer_stat_ad
            where `transfer_date`<=:end and `transfer_date`>=:start and ad_id in (:ad_id)";
    $state = $DB->prepare($sql);
    $state->execute( [
      ':start' => $param['start'],
      ':end' => $param['end'],
      ':ad_id' => $param['ad_id'],
    ] );
    $transfer = $state->fetchAll(PDO::FETCH_ASSOC);

    $DB_write = $this->get_write_pdo();
    if ($transfer_total) {
      $rmb_total = 0;
      foreach ($transfer as $key => $item) {
        if ($key == count($transfer) - 1) {
          $rmb = $param['rmb'] - $rmb_total;
        } else {
          $rmb = round($param['rmb'] * $item['transfer_total'] / $transfer_total);
          $rmb_total += $rmb;
        }
        $item['source_id'] = $param['id'];
        $item['rmb'] = $rmb;
        unset($item['transfer_total']);
        if (!SQLHelper::insert($DB_write, $is_activity ? 't_ad_activity_detail' : 't_ad_cut_detail', $item)) {
          throw new Exception('写入分广告数据失败', 100);
        }
      }
    } else {
      $ad_ids = explode(',', $param['ad_id']);
      $count = count($ad_ids);
      $dates = (strtotime($param['end']) - strtotime($param['start'])) / 86400 + 1;
      $rmb = round($param['rmb'] / $count / $dates);
      foreach ($ad_ids as $key => $ad_id) {
        for($date = $param['start']; $date <= $param['end'];) {
          $item = array(
            'source_id' => $param['id'],
            'ad_id' => $ad_id,
            'date' => $date,
            'rmb' => $rmb,
          );
          if ($date == $param['end'] && $key == $count - 1) {
            $item['rmb'] = $param['rmb'] - $rmb * ($count * $dates - 1);
          }
          if (!SQLHelper::insert($DB_write, $is_activity ? 't_ad_activity_detail' : 't_ad_cut_detail', $item)) {
            throw new Exception('写入分广告数据失败', 101);
          }
          $date = date("Y-m-d", strtotime($date) + 86400);
        }
      }
    }
  }

  public function get_activity_cut($id, $is_activity) {
    $table = $is_activity ? 't_ad_activity' : 't_ad_cut';
    $sql = "select `id`,`start`,`end`,`ad_id`,`rmb`
            from $table
            where id=:id";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $state->execute( [
      ':id' => $id,
    ] );
    return $state->fetch(PDO::FETCH_ASSOC);
  }

  public function get_cut_by_month($start, $end) {
    $sql = "select a.`ad_id`,left(`date`,7) as `month`,sum(a.`rmb`) as `rmb`
      from t_ad_cut_detail as a
        join t_ad_cut as b on a.source_id=b.id
      where `date`>=:start and `date`<=:end and status=" . ActivityCutModel::STATUS_NORMAL . "
      group by a.`ad_id`,`month`";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $state->execute(array(':start' => $start, ':end' => $end));
    $result = $state->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_GROUP);

    $cut = array();
    foreach ($result as $ad_id => $item) {
      foreach ($item as $value) {
        $cut[$ad_id][$value['month']] = $value['rmb'];
      }
    }

    return $cut;
  }

}