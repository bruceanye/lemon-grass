<?php
/**
 * Created by PhpStorm.
 * User: hemengyuan
 * Date: 15/5/4
 * Time: 下午4:18
 */

namespace diy\service;

use PDO;

class ADTransferStat extends Base {
  public function get_ad_transfer_stat($start, $end, $filters = array()) {
    list($conditions, $params) = $this->parse_filter($filters, ['is_append' => true]);
    $sql = "SELECT `ad_id`,SUM(`transfer_total`) AS `transfer`,SUM(`rmb_total`) AS `rmb`
            FROM `s_transfer_stat_ad` AS a JOIN `t_adinfo` AS b ON a.ad_id=b.id
            WHERE `transfer_date`>=:start AND `transfer_date`<=:end $conditions
            GROUP BY `ad_id`";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $params = array_merge($params, [ ':start' => $start, ':end' => $end ] );
    $state->execute($params);
    return $state->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_UNIQUE);
  }

  public function get_ad_transfer_stat_of_quote($start, $end, $filters = array()) {
    list($conditions, $params) = $this->parse_filter($filters, ['is_append' => true]);
    $sql = "SELECT `ad_id`,SUM(`transfer_total`) AS `transfer`,SUM(`rmb_total`) AS `rmb`,
            `ad_name`,`ad_app_type`,`cid`,a.`id`
            FROM `s_transfer_stat_ad` AS a
              JOIN `t_adinfo` AS b ON a.ad_id=b.id
              LEFT JOIN `t_ad_source` c on a.`ad_id`=c.`id`
            WHERE `transfer_date`>=:start AND `transfer_date`<=:end $conditions
            GROUP BY `ad_id`";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $params = array_merge($params, [ ':start' => $start, ':end' => $end ] );
    $state->execute($params);
    return $state->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_UNIQUE);
  }

  public function get_delivered_ad($date_diff) {
    $DB = $this->get_read_pdo();
    $date_diff = (int)$date_diff;
    $sql = "select pack_name from s_transfer_stat_ad as a join t_adinfo as b on a.ad_id=b.id where transfer_date>=date_sub(curdate(),interval $date_diff day) group by pack_name";
    return $DB->query( $sql )->fetchAll(PDO::FETCH_COLUMN);
  }

  public function get_ad_transfer_stat_by_ad($ad_id, $start, $end) {
    $sql = "SELECT `transfer_date`,`transfer_total`,`rmb_total`
            FROM `s_transfer_stat_ad`
            WHERE `transfer_date`>=:start AND `transfer_date`<=:end and ad_id=:ad_id";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $state->execute( [ ':start' => $start, ':end' => $end, ':ad_id' => $ad_id ] );
    return $state->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_UNIQUE);
  }

  public function get_all_ad_transfer_by_user($start, $end, $user_ids, $other_filters = null) {
    if (strtotime($start) > time() - 86400 * 89) {
      $filters = array_merge((array)$other_filters, ['user_id' => $user_ids]);
      list($conditions, $params) = $this->parse_filter($filters, ['is_append' => true]);
      $sql = "select ad_id,sum(rmb_total) as rmb,sum(transfer_total) as transfer
        from s_transfer_stat_app_ad as a
        join t_appinfo as b on a.app_id=b.id
        where transfer_date>=:start and transfer_date<=:end $conditions
        group by ad_id";
      $DB = $this->get_read_pdo();
      $state = $DB->prepare($sql);
      $state->execute( array_merge($params, [ ':start' => $start, ':end' => $end ] ));
      return $state->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_UNIQUE);
    } else {
      $app_service = new App();
      $app_ids = $app_service->get_user_app_ids($user_ids);
      $app_ids = array_chunk($app_ids, 1024);
      $ad_ids_sql    = $other_filters ? '{
        "query_string": {
          "default_field": "ad_id",
          "query": "(' . implode( ') OR (', $other_filters['ad_id'] ) . ')"
        }
      },' : '';
      $data = array_map(function ($chunk) use ($start, $end, $ad_ids_sql) {
        return $this->queryES( $start, $end, $ad_ids_sql, $chunk );
      }, $app_ids);
      $first = array_shift($data);
      return array_reduce($data, function ($memo, $chunk) {
        foreach ( $chunk as $key => $item ) {
          if (isset($memo[$key])) {
            $memo[$key]['transfer'] += $item['transfer'];
            $memo[$key]['rmb'] += $item['rmb'];
          } else {
            $memo[$key] = $item;
          }
        }
        return $memo;
      }, $first);
    }
  }

  public function get_ad_transfer_by_user($id, $start, $end, $user_id) {
    $user_id = is_array($user_id) ? $user_id : [$user_id];
    list ($conditions, $params) = $this->parse_filter([
      'user_id' => $user_id,
      'ad_id' => $id
    ], ['is_append' => true]);
    $params[':start'] = $start;
    $params[':end'] = $end;
    $sql = "select transfer_date,sum(rmb_total) as rmb,sum(transfer_total) as transfer 
            from s_transfer_stat_app_ad as a
              join t_appinfo as b on a.app_id=b.id
            where transfer_date>=:start and transfer_date<=:end $conditions 
            group by transfer_date";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $state->execute($params);
    return $state->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_UNIQUE|PDO::FETCH_GROUP);
  }

  public function getQuotedTransfer($start, $end) {
    $sql = "select a.`ad_id`,sum(a.`transfer_total`)
            from `s_transfer_stat_ad` as a
              join `t_adquote` as b on a.`ad_id`=b.`ad_id` and a.`transfer_date`=b.`quote_date`
            where a.`transfer_date`>=:start and a.`transfer_date`<=:end and b.`nums`>0
            group by a.`ad_id`";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $state->execute([':start' => $start, ':end' => $end]);
    return $state->fetchAll(PDO::FETCH_KEY_PAIR);
  }

  /**
   * @param $start
   * @param $end
   * @param $ad_ids_sql
   * @param $app_ids
   *
   * @return array
   */
  private function queryES( $start, $end, $ad_ids_sql, $app_ids ) {
    $app_ids_sql   = implode( ') OR (', $app_ids );
    $params        = '{
        "index" : "stat_data",
        "type" : "transfer_stat_app_ad",
        "body" : {
          "query" : {
            "bool": {
              "must": [
                {
                  "query_string": {
                    "default_field": "app_id",
                    "query": "(' . $app_ids_sql . ')"
                  }
                },
                ' . $ad_ids_sql . ' 
                {
                  "range": {
                    "transfer_date": {
                      "gte": "' . $start . '",
                      "lte": "' . $end . '"
                    }
                  }
                }
              ]
            }
          },
          "aggs": {
            "all_ad_ids": {
              "terms": {
                "field": "ad_id",
                "size": 100000
              },
              "aggs": {
                "sum_rmb": {
                  "sum": {
                    "field": "rmb_total"
                  }
                },
                "sum_transfer": {
                  "sum": {
                    "field": "transfer_total"
                  }
                }
              }
            }
          },
          "size": 0
        }
      }';
    $es_service    = new ES();
    $transfer_stat = $es_service->query( $params );
    $transfer      = array();
    if ( is_array( $transfer_stat['aggregations']['all_ad_ids']['buckets'] ) ) {
      foreach ( $transfer_stat['aggregations']['all_ad_ids']['buckets'] as $transfer_ad ) {
        $transfer[ $transfer_ad['key'] ]['transfer'] += $transfer_ad['sum_transfer']['value'];
        $transfer[ $transfer_ad['key'] ]['rmb'] += $transfer_ad['sum_rmb']['value'];
      }
    }

    return $transfer;
  }
}