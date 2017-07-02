<?php
/**
 * Created by PhpStorm.
 * User: hemengyuan
 * Date: 15/5/11
 * Time: 下午3:13
 */

namespace diy\service;

use PDO;

class HappyLockStat extends Base {

  const HAPPY_LOCK_USER_IDS = array('4bcb3bbb75815539a10d4e60bcdeb5fe', 'b1e54b21bb97c7ac4bd386ca5b8ac4ab', 'ebcc380163d1a8252dbee807f66c4530', '1b97e1ed9bae18afeccda62567b32fb8');
  const HAPPY_LOCK_APP_IDS = ['5763c2eb596b7a4e511f588d4ee7e50f', 'c81bc556d5dc52b854f591320d4c951b'];
  const MAGIC_USER_ID = '6be44882e29466f47b47894e48751c49';
  const PUBLISHER_USER_ID = 'dc6d2b398f6a5bec522f10227117ebed';

  protected $DB_happy_lock;

  /**
   * @return PDO
   */
  protected function get_happy_lock_pdo() {
    $this->DB_happy_lock = $this->DB_happy_lock ? $this->DB_happy_lock : require PROJECT_DIR . '/app/connector/pdo_happy_lock.php';
    return $this->DB_happy_lock;
  }

  public function get_all_ad_happy_lock_task($start, $end, $type, $user_ids = self::HAPPY_LOCK_USER_IDS) {
    $today = date('Y-m-d');
    if ($start == $today && $end == $today) {
      return [];
    }
    $app_service = new App();
    $app_ids = $app_service->get_user_app_ids($user_ids);
    $app_ids_sql = implode(') OR (', $app_ids);
    $params = '{
      "index" : "task_stat",
      "type" : "' . $type . '",
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
              {
                "range": {
                  "task_date": {
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
                  "field": "rmb"
                }
              }
            }
          }
        },
        "size": 0
      }
    }';


    $ES_service = new ES();
    $es_task = $ES_service->query($params);

    $happy_lock_task = array();
    foreach ($es_task['aggregations']['all_ad_ids']['buckets'] as $ad_task) {
      $happy_lock_task[$ad_task['key']] = $ad_task['sum_rmb']['value'];
    }

    return $happy_lock_task;
  }

  public function get_happy_lock_outcome($start, $end) {
    $sql = "select sum(pay) from z_present_exchange where update_time>=:start and update_time<=:end and pay_status=3";
    $DB = $this->get_happy_lock_pdo();
    $state = $DB->prepare($sql);
    $state->execute(array(':start' => $start, ':end' => $end . ' 23:59:59'));
    return $state->fetch(PDO::FETCH_COLUMN);
  }

  public function get_ad_happy_lock_task_stat($id, $start, $end, $type) {
    $app_service = new App();
    $app_ids = $app_service->get_user_app_ids(self::HAPPY_LOCK_USER_IDS);
    $app_ids_sql = implode(') OR (', $app_ids);
    $params = '{
      "index" : "task_stat",
      "type" : "' . $type . '",
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
              {
                "term": {
                  "ad_id": "' . $id . '"
                }
              },
              {
                "range": {
                  "task_date": {
                    "gte": "' . $start . '",
                    "lte": "' . $end . '"
                  }
                }
              }
            ]
          }
        },
        "aggs": {
          "all_dates": {
            "terms": {
              "field": "task_date",
              "size": 100
            },
            "aggs": {
              "sum_rmb": {
                "sum": {
                  "field": "rmb"
                }
              }
            }
          }
        },
        "size": 0
      }
    }';

    $es_service = new ES();
    $es_task = $es_service->query($params);
    $happy_lock_task = array();
    if (is_array($es_task['aggregations']['all_dates']['buckets'])) {
      foreach ($es_task['aggregations']['all_dates']['buckets'] as $ad_task) {
        $happy_lock_task[substr($ad_task['key_as_string'], 0, 10)] = $ad_task['sum_rmb']['value'];
      }
    }
    return $happy_lock_task;
  }
}