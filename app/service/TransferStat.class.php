<?php
/**
 * Created by PhpStorm.
 * User: chensheng
 * Date: 15/8/21
 * Time: 下午5:54
 */
namespace diy\service;

use diy\utils\Utils;
use PDO;
use Exception;

class TransferStat extends Base {
  const RETURN_METHOD_GROUP = "GROUP";
  const RETURN_METHOD_COLUMN = "COLUMN";
  const RETURN_METHOD_ASSOC = "ASSOC";
  const COLUMN_AD_ID = "ad_id";
  const COLUMN_APP_ID = "app_id";
  const COLUMN_TRANSFER_DATE = "transfer_date";

  var $output_mode=true;
  public function get_ad_transfer_by_ads($start, $end, $adids) {
    $DB = $this->get_read_pdo();
    $builder = new TransferQueryBuilder();
    return $this->get_transfer($DB,
      $builder
        ->where_date($start, $end)
        ->where(self::COLUMN_AD_ID, $adids)
        ->group(self::COLUMN_AD_ID),
      self::RETURN_METHOD_GROUP);
  }

  public function get_ad_transfer_by_date($start, $end, $adid) {
    $DB = $this->get_read_pdo();
    $builder = new TransferQueryBuilder();
    return $this->get_transfer($DB,
      $builder
        ->where_date($start, $end)
        ->where(self::COLUMN_AD_ID, $adid)
        ->group(self::COLUMN_TRANSFER_DATE),
      self::RETURN_METHOD_GROUP);
  }

  public function get_transfer(PDO $DB, TransferQueryBuilder $query_builder, $return_method) {
    $stmt = $DB->query($query_builder->output(true));
    switch($return_method) {
      case self::RETURN_METHOD_COLUMN:
        return $stmt->fetch(PDO::FETCH_COLUMN);
      case self::RETURN_METHOD_ASSOC:
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
      case self::RETURN_METHOD_GROUP:
        return $stmt->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_UNIQUE | PDO::FETCH_GROUP);
      default:
        throw new Exception("无效的参数return_method");
    }
  }

  public function get_ios_cpa_by_day($start, $end) {
    $DB = $this->get_read_pdo();

    $sql = "SELECT ad_id,count(distinct device_id)
            FROM t_income_transfer_ios_log
            WHERE adnotify_time>='$start' AND adnotify_time<='$end 23:59:59' GROUP BY ad_id";
    return $DB->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR);
  }

  public function get_all_ad_happy_lock_transfer($start, $end) {
    $DB = $this->get_read_pdo();
    list($conditions, $params) = $this->parse_filter([
      'app_id' => HappyLockStat::HAPPY_LOCK_APP_IDS,
    ], ['is_append' => true]);
    $sql = "SELECT `ad_id`,sum(`transfer_total`) as num,sum(`rmb_total`) as rmb
            FROM `s_transfer_stat_app_ad`
            WHERE `transfer_date`>=:start AND `transfer_date`<=:end $conditions
            GROUP BY `ad_id`";
    $state = $DB->prepare($sql);
    $state->execute(array_merge([':start' => $start, ':end' => $end], $params));
    return $state->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_UNIQUE);
  }

  public function get_offer_click_total($start, $end) {
    $DB = $this->get_read_pdo();
    $sql = "SELECT `ad_id`,sum(`click_total`)
            FROM `s_offer_click_log_stat_ad`
            WHERE `click_date`>=:start AND `click_date`<=:end
            GROUP BY `ad_id`";
    $state = $DB->prepare($sql);
    $state->execute(array(
      ':start' => $start,
      ':end' => $end
    ));
    return $state->fetchAll(PDO::FETCH_KEY_PAIR);
  }

  public function get_offer_click_total_by_id($start, $end, $id) {
    $DB = $this->get_read_pdo();
    $sql = "SELECT `click_date`,`click_total`
            FROM `s_offer_click_log_stat_ad`
            WHERE `ad_id`='$id'
            AND `click_date`>=:start AND `click_date`<=:end";
    $state = $DB->prepare($sql);
    $state->execute(array(
      ':start' => $start,
      ':end' => $end
    ));
    return $state->fetchAll(PDO::FETCH_KEY_PAIR);
  }

  public function get_income_stat_ios(array $filters = [], $group = 'ad_id') {
    list($conditions, $params) = $this->parse_filter($filters, ['date_field' => 'adnotify_date']);
    $DB = $this->get_read_pdo();
    $sql = "SELECT `$group`,sum(`num`)
            FROM `s_income_transfer_stat_ios`
            WHERE $conditions
            GROUP BY `$group`";
    $state = $DB->prepare($sql);
    $state->execute($params);
    return $state->fetchAll(PDO::FETCH_KEY_PAIR);
  }

  public function get_offer_install_stat_ad($start, $end) {
    $DB = $this->get_stat_pdo();
    $sql = "SELECT `ad_id`,sum(`install_total`)
            FROM `s_offer_install_stat_ad`
            WHERE `install_date`>=:start AND `install_date`<=:end
            GROUP BY `ad_id`";
    $state = $DB->prepare($sql);
    $state->execute(array(
      ':start' => $start,
      ':end' => $end
    ));
    return $state->fetchAll(PDO::FETCH_KEY_PAIR);
  }

  public function get_offer_install_stat_ad_by_id($start, $end, $id) {
    $DB = $this->get_stat_pdo();
    $sql = "SELECT `install_date`,`install_total`
            FROM `s_offer_install_stat_ad`
            WHERE `ad_id`=:id AND `install_date`>=:start AND `install_date`<=:end";
    $state = $DB->prepare($sql);
    $state->execute(array(
      ':start' => $start,
      ':end' => $end,
      ':id' => $id
    ));
    return $state->fetchAll(PDO::FETCH_KEY_PAIR);
  }

  public function get_ad_happy_lock_transfer_by_date($start, $end, $id) {
    $DB = $this->get_read_pdo();
    list($conditions, $params) = $this->parse_filter([
      'app_id' => HappyLockStat::HAPPY_LOCK_APP_IDS,
      'ad_id' => $id,
    ], ['is_append' => true]);
    $sql = "SELECT `transfer_date`,sum(`rmb_total`)
            FROM `s_transfer_stat_app_ad`
            WHERE `transfer_date`>=:start AND `transfer_date`<=:end $conditions
            GROUP BY `transfer_date`";
    $state = $DB->prepare($sql);
    $state->execute(array_merge([':start' => $start, ':end' => $end], $params));
    return $state->fetchAll(PDO::FETCH_KEY_PAIR);
  }

  public function get_ios_cpa_by_ad($start, $end, $id) {
    $DB = $this->get_read_pdo();
    $sql = "SELECT `adnotify_date`,`num`
            FROM `s_income_transfer_stat_ios`
            WHERE `ad_id`=:id AND `adnotify_date`>=:start AND `adnotify_date`<=:end";
    $state = $DB->prepare($sql);
    $state->execute(array(
      ':start' => $start,
      ':end' => $end,
      ':id' => $id
    ));
    return $state->fetchAll(PDO::FETCH_KEY_PAIR);
  }

  public function get_transfer_location_by_ad($start, $end, $id, $ad_app_type) {
    $ad_app_type = $ad_app_type == 1 ? 'android' : 'ios';
    $month = substr($start, 0, 7);
    $params = '{
      "index" : "dianjoy-api-runtime-' . $ad_app_type . '_adnotify_log-' . $month . '",
      "body" : {
  "size"                : 1,
  "query"               : {
    "bool"                : {
      "must_not"            : [ ],
      "should"              : [ ],
      "must"                : [
        {
          "query_string"        : {
            "default_field"       : "ad_id",
            "query"               : "' . $id . '"
          }
        },
        {
          "query_string"        : {
            "default_field"       : "result_type",
            "query"               : "success"
          }
        },
        {
          "range"               : {
            "insert_els_log_time" : {
              "from"                : "' . $start . 'T00:00:00",
              "to"                  : "' . $end . 'T23:59:59"
            }
          }
        }
      ]
    }
  },
  "from"                : 0,
  "aggs"                : {
    "province_agg"        : {
      "terms"               : {
        "field"               : "province.raw"
      },
      "aggs"                : {
        "sum_rmb"             : {
          "sum"                 : {
            "field"               : "step_rmb"
          }
        }
      }
    }
  }
}
    }';
    $params = str_replace(' ', '', $params);
    $params = str_replace("\n", '', $params);
    $params = urlencode(trim($params));
    $ch = curl_init();
    curl_setopt ($ch, CURLOPT_URL, "http://a.dianjoy.com/dev/api/elasticsearch/rest_api_search.php?params=" . $params);
    curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT,10);
    $a = json_decode(curl_exec($ch), true);

    $result = array();
    foreach ($a['aggregations']['province_agg']['buckets'] as $value) {
      $result[] = array(
        'location' => $value['key'],
        'transfer' => $value['doc_count'],
        'rmb' => $value['sum_rmb']['value'],
      );
    }

    return $result;
  }

  public function get_offer_install_stat_ad_h($date, $id) {
    $DB_STAT = $this->get_stat_pdo();

    $sql = "SELECT `install_h`,`install_total`
            FROM `s_offer_install_stat_ad_h`
            WHERE `install_date`=:date AND `ad_id`=:id";
    $state = $DB_STAT->prepare($sql);
    $state->execute(array(
      ':date' => $date,
      ':id' => $id
    ));
    return $state->fetchAll(PDO::FETCH_KEY_PAIR);
  }

  public function get_transfer_stat_ad_h($date, $id) {
    $DB_STAT = $this->get_stat_pdo();

    $sql = "SELECT `h`,`transfer_total`
            FROM `s_transfer_stat_ad_h`
            WHERE `transfer_date`=:date AND `ad_id`=:id";
    $state = $DB_STAT->prepare($sql);
    $state->execute(array(
      ':date' => $date,
      ':id' => $id
    ));
    return $state->fetchAll(PDO::FETCH_KEY_PAIR);
  }

  public function get_offer_click_stat_ad_h($date, $id) {
    $DB_STAT = $this->get_stat_pdo();

    $sql = "SELECT `h`,`click_total`
            FROM `s_offer_click_stat_ad_h`
            WHERE `click_date`=:date AND `ad_id`=:id";
    $state = $DB_STAT->prepare($sql);
    $state->execute(array(
      ':date' => $date,
      ':id' => $id
    ));
    return $state->fetchAll(PDO::FETCH_KEY_PAIR);
  }

  public function get_income_transfer_stat_ios_h($date, $id) {
    $DB_STAT = $this->get_stat_pdo();

    $sql = "SELECT `h`,`num`
            FROM `s_income_transfer_stat_ios_ad_h`
            WHERE `adnotify_date`=:date AND `ad_id`=:id";
    $state = $DB_STAT->prepare($sql);
    $state->execute(array(
      ':date' => $date,
      ':id' => $id
    ));
    return $state->fetchAll(PDO::FETCH_KEY_PAIR);
  }

  public function get_app_transfer_by_ad($start,$end,$id) {
    $DB = $this->get_read_pdo();
    //最近90天的数据从数据库取，否则从ES读
    if (strtotime($start) > time() - 86400 * 89) {
      $builder = new TransferQueryBuilder();
      return $this->get_transfer($DB,
        $builder
          ->where_date($start,$end)
          ->where(self::COLUMN_AD_ID,$id)
          ->group(self::COLUMN_APP_ID),
        self::RETURN_METHOD_GROUP);
    } else {
      $params = '{
      "index" : "stat_data",
      "type" : "transfer_stat_app_ad",
      "body" : {
        "query" : {
          "bool": {
            "must": [
              {
                "term": {
                  "ad_id": "' . $id . '"
                }
              },
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
          "all_app_ids": {
            "terms": {
              "field": "app_id",
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
      $params = str_replace(' ', '', $params);
      $params = str_replace("\n", '', $params);
      $params = urlencode(trim($params));
      $ch = curl_init();
      curl_setopt ($ch, CURLOPT_URL, "http://a.dianjoy.com/dev/api/elasticsearch/rest_api_search.php?params=" . $params);
      curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT,10);
      $transfer_stat = json_decode(curl_exec($ch), true);
      $transfer = array();
      foreach ($transfer_stat['aggregations']['all_app_ids']['buckets'] as $transfer_app) {
        $transfer[$transfer_app['key']]['transfer'] += $transfer_app['sum_transfer']['value'];
        $transfer[$transfer_app['key']]['rmb'] += $transfer_app['sum_rmb']['value'];
      }
      return $transfer;
    }
  }

  public function get_ad_transfer_by_sale($start, $end, $owner) {
    $DB = $this->get_read_pdo();

    $sql = "SELECT `transfer_date`,sum(`transfer_total`) AS `transfer`,SUM(`rmb_total`) AS `rmb`
            FROM `s_transfer_stat_ad` AS a JOIN `t_ad_source` as b on a.ad_id=b.id
            WHERE `transfer_date`>=:start AND `transfer_date`<=:end AND `owner`=:owner
            GROUP BY `transfer_date`";
    $state = $DB->prepare($sql);
    $state->execute(array(
      ':start' => $start,
      ':end' => $end,
      ':owner' => $owner
    ));
    return $state->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_UNIQUE|PDO::FETCH_GROUP);
  }

  public function get_transfer_by_app_day($start, $end, $app_id) {
    $DB = $this->get_read_pdo();
    $builder = new TransferQueryBuilder();
    return $this->get_transfer($DB,
      $builder
        ->where_date($start, $end)
        ->where(self::COLUMN_APP_ID, $app_id)
        ->group(self::COLUMN_TRANSFER_DATE),
      self::RETURN_METHOD_GROUP);
  }

  public function get_last_hour_ios_click() {
    $DB_STAT = $this->get_stat_pdo();

    $time = date("Y-m-d H", time() - 3600);
    $sql = "SELECT sum(num)
            FROM s_ios_click
            WHERE stat_date>=:stat_date";
    $state = $DB_STAT->prepare($sql);
    $state->execute(array(
      ':stat_date' => $time
    ));
    return $state->fetch(PDO::FETCH_COLUMN);
  }

  public function get_last_hour_ios_transfer() {
    $DB_STAT = $this->get_stat_pdo();

    $time = date("Y-m-d H", time() - 3600);
    $sql = "SELECT sum(num)
            FROM s_ios_transfer
            WHERE stat_date>=:stat_date";
    $state = $DB_STAT->prepare($sql);
    $state->execute(array(
      ':stat_date' => $time
    ));
    return $state->fetch(PDO::FETCH_COLUMN);
  }

  public function get_iOS_CPA_withKeyword( array $filters = null) {
    $DB = $this->get_stat_pdo();
    list($conditions, $params) = $this->parse_filter($filters);
    $sql = "SELECT `ad_id`,SUM(`num`) AS `num`,`search_key`
            FROM `s_ios_adnotify_by_key`
            WHERE $conditions
            GROUP BY `ad_id`,`search_key`";
    $state = $DB->prepare( $sql );
    $state->execute($params);
    return $state->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_GROUP);
  }

  public function get_iOS_CPA_withKeywordByHour( $ad_id, $date ) {
    $DB = $this->get_stat_pdo();
    $sql = "SELECT `notify_hour`,`num`,`search_key`
            FROM `s_ios_adnotify_by_key_hourly`
            WHERE `ad_id`=:ad_id AND `notify_date`=:date";
    $state = $DB->prepare( $sql );
    $state->execute([
      ':ad_id' => $ad_id,
      ':date' => $date,
    ]);
    return $state->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_GROUP);
  }

  protected function parse_filter( array $filters = null, array $options = [ ] ) {
    $defaults = ['to_string' => true, 'date_field' => 'notify_date'];
    $options = array_merge( $defaults, $options );
    $spec = ['start', 'end'];
    $omit = Utils::array_pick( $filters, $spec );
    $filters = Utils::array_omit( $filters, $spec );
    list($conditions, $params) = parent::parse_filter( $filters, ['to_string' => false] );
    foreach ( $omit as $key => $value ) {
      switch ($key) {
        case 'start':
          $conditions[] = "`{$options['date_field']}`>=:start";
          $params[':start'] = $value;
          break;

        case 'end':
          $conditions[] = "`{$options['date_field']}`<=:end";
          $params[':end'] = $value;
          break;
      }
    }
    if ($options['to_string']) {
      $conditions = count( $conditions ) ? implode( ' AND ', $conditions ) : 1;
    }
    if (!is_array( $conditions ) && $conditions && $options['is_append']) {
      $conditions = ' AND ' . $conditions;
    }
    return [$conditions, $params];
  }
}