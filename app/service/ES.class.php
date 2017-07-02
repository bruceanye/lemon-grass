<?php
/**
 * Created by PhpStorm.
 * User: hemengyuan
 * Date: 15/6/16
 * Time: 下午2:42
 */

namespace diy\service;

class ES extends Base {
  public function query($params) {
    $params = str_replace(' ', '', $params);
    $params = str_replace("\n", '', $params);
    $params = urlencode(trim($params));
    $ch = curl_init();
    curl_setopt ($ch, CURLOPT_URL, 'http://a.dianjoy.com/dev/api/elasticsearch/rest_api_search.php');
    curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'params=' . $params);
    $data = curl_exec( $ch );
    return json_decode( $data, true);
  }

  public function query_sql($index, $type, $param, $source, $size = 10) {
    $params =
      '{
        "index" : "' . $index . '",
        "type" : "' . $type . '",
        "body" : {
          "size" : ' . $size . ',
          "query" : {
            "bool": {
              "must_not" : [ ],
              "should" : [ ],
              "must": [';
    foreach ($param as $key => $value) {
      if (is_array($value)) {
        foreach ($value as $v) {
          $params .=
            '{
          "query_string": {
            "default_field": "' . $key . '",
            "query": "' . $v . '"
          }
        },';
        }
      } else {
        $params .=
          '{
          "query_string": {
            "default_field": "' . $key . '",
            "query": "' . $value . '"
          }
        },';
      }
    }
    $params = substr($params, 0, -1);
    $params .=
      ' ]
      }
    },
    "from" : 0,
    "_source" : [
    "';
    $params .= implode('","', $source);
    $params .=
        '"
        ]
      }
    }';
    return $this->query($params);
  }
}