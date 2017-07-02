<?php
/**
 * Created by PhpStorm.
 * User: chensheng
 * Date: 15/8/24
 * Time: 上午11:02
 */

namespace diy\service;

use PDO;

class AdminTaskStat extends Base {
  public function __construct() {
    $this->DB = $this->get_read_pdo();
    $this->DB_write = $this->get_write_pdo();
  }

  public function get_ad_limited_task_outcome_by_date($start, $end, $id) {
    $sql = "SELECT `task_date`,sum(`rmb`) as `outcome`
            FROM `s_limited_task_stat`
            WHERE `task_date`>='$start' AND `task_date`<='$end' AND `ad_id`='$id'
            GROUP BY `task_date`";
    return $this->DB->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR);
  }

  public function get_ad_task_outcome_by_date($start, $end, $id) {
    $sql = "SELECT a.`task_date`,sum(a.`rmb`) AS `outcome`
            FROM `s_task_stat` AS a JOIN `t_task` AS b
            ON a.`task_id`=b.`id`
            WHERE a.`task_date`>='$start' AND a.`task_date`<='$end' and b.`ad_id`='$id'
            GROUP BY a.`task_date`";
    return $this->DB->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR);
  }

  public function get_ad_task_stat($start, $end) {
    $sql = "SELECT `ad_id`,sum(`num`) as num,sum(`rmb`) as rmb,sum(`ready`) as ready
            FROM `s_task_stat` as a join `t_task` as b
            ON a.`task_id`=b.`id`
            WHERE `task_date`>='$start' AND `task_date`<='$end'
            GROUP BY `ad_id`";
    return $this->DB->query($sql)->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_UNIQUE|PDO::FETCH_GROUP);
  }

  public function get_ad_limited_task_stat($start, $end) {
    $sql = "SELECT `ad_id`,sum(`num`) as num,sum(`rmb`) as rmb,sum(`ready`) as ready
            FROM `s_limited_task_stat`
            WHERE `task_date`>='$start' AND `task_date`<='$end'
            GROUP BY `ad_id`";
    return $this->DB->query($sql)->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_UNIQUE|PDO::FETCH_GROUP);
  }

  public function get_ad_happy_lock_task_by_date($start, $end, $id) {
    $params = '{
      "index" : "task_stat",
      "type" : "s_task_stat_task_app",
      "body" : {
        "query" : {
          "bool": {
            "must": [
              {
                "query_string": {
                  "default_field": "app_id",
                  "query": "(5763c2eb596b7a4e511f588d4ee7e50f) OR (c81bc556d5dc52b854f591320d4c951b)"
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
    $params = str_replace(' ', '', $params);
    $params = str_replace("\n", '', $params);
    $params = urlencode(trim($params));
    $ch = curl_init();
    curl_setopt ($ch, CURLOPT_URL, "http://a.dianjoy.com/dev/api/elasticsearch/rest_api_search.php?params=" . $params);
    curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT,10);
    return json_decode(curl_exec($ch), true);
  }

  public function get_ad_happy_lock_limited_task_by_date($start, $end, $id) {
    $params = '{
      "index" : "task_stat",
      "type" : "s_limited_task_stat_ad_app",
      "body" : {
        "query" : {
          "bool": {
            "must": [
              {
                "query_string": {
                  "default_field": "app_id",
                  "query": "(5763c2eb596b7a4e511f588d4ee7e50f) OR (c81bc556d5dc52b854f591320d4c951b)"
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
    $params = str_replace(' ', '', $params);
    $params = str_replace("\n", '', $params);
    $params = urlencode(trim($params));
    $ch = curl_init();
    curl_setopt ($ch, CURLOPT_URL, "http://a.dianjoy.com/dev/api/elasticsearch/rest_api_search.php?params=" . $params);
    curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT,10);
    return json_decode(curl_exec($ch), true);
  }

  public function get_all_ad_happy_lock_task($start, $end) {
    $params = '{
      "index" : "task_stat",
      "type" : "s_task_stat_task_app",
      "body" : {
        "query" : {
          "bool": {
            "must": [
              {
                "query_string": {
                  "default_field": "app_id",
                  "query": "(5763c2eb596b7a4e511f588d4ee7e50f) OR (c81bc556d5dc52b854f591320d4c951b)"
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
    $params = str_replace(' ', '', $params);
    $params = str_replace("\n", '', $params);
    $params = urlencode(trim($params));
    $ch = curl_init();
    curl_setopt ($ch, CURLOPT_URL, "http://a.dianjoy.com/dev/api/elasticsearch/rest_api_search.php?params=" . $params);
    curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT,10);
    return json_decode(curl_exec($ch), true);
  }

  public function get_all_ad_happy_lock_limited_task($start, $end) {
    $params = '{
      "index" : "task_stat",
      "type" : "s_limited_task_stat_ad_app",
      "body" : {
        "query" : {
          "bool": {
            "must": [
              {
                "query_string": {
                  "default_field": "app_id",
                  "query": "(5763c2eb596b7a4e511f588d4ee7e50f) OR (c81bc556d5dc52b854f591320d4c951b)"
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
    $params = str_replace(' ', '', $params);
    $params = str_replace("\n", '', $params);
    $params = urlencode(trim($params));
    $ch = curl_init();
    curl_setopt ($ch, CURLOPT_URL, "http://a.dianjoy.com/dev/api/elasticsearch/rest_api_search.php?params=" . $params);
    curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT,10);
    return json_decode(curl_exec($ch), true);
  }

  public function get_ad_task_stat_by_app($start, $end, $id) {
    $params = '{
      "index" : "task_stat",
      "type" : "s_task_stat_task_app",
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
          "all_app_ids": {
            "terms": {
              "field": "app_id",
              "size": 10000
            },
            "aggs": {
              "sum_rmb": {
                "sum": {
                  "field": "rmb"
                }
              },
              "sum_ready": {
                "sum": {
                  "field": "ready"
                }
              },
              "sum_num": {
                "sum": {
                  "field": "num"
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
    return json_decode(curl_exec($ch), true);
  }

  public function get_ad_limited_task_stat_by_app($start, $end, $id) {
    $params = '{
      "index" : "task_stat",
      "type" : "s_limited_task_stat_ad_app",
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
          "all_app_ids": {
            "terms": {
              "field": "app_id",
              "size": 10000
            },
            "aggs": {
              "sum_rmb": {
                "sum": {
                  "field": "rmb"
                }
              },
              "sum_ready": {
                "sum": {
                  "field": "ready"
                }
              },
              "sum_num": {
                "sum": {
                  "field": "num"
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
    return json_decode(curl_exec($ch), true);
  }

  public function get_ad_task_outcome_by_sale($start, $end, $owner) {
    $DB = $this->get_read_pdo();

    $sql = "SELECT b.ad_id,sum(a.rmb)
            FROM s_task_stat AS a JOIN (t_task AS b,t_ad_source AS c) ON a.task_id=b.id AND b.ad_id=c.id
            WHERE a.task_date>='$start' AND a.task_date<='$end' AND c.owner='$owner'
            GROUP BY b.ad_id";
    return $DB->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR);
  }
}
