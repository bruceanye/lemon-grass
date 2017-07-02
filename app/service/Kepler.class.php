<?php
/**
 * Created by PhpStorm.
 * User: chensheng
 * Date: 16/5/18
 * Time: 上午11:08
 */

namespace diy\service;

use diy\utils\Utils;

class Kepler extends Base {
  const ADDRESS = 'http://kepler.dianjoy.cn/job.php';
  const READ_TIME = 2;

  public function getData($sql) {
    $postString = 'key=' . KEPLER_KEY . '&email=' . Admin::DEVELOPER_CS_MAIL . '&do=add&sql=' . $sql;
    $job = Utils::request_by_curl(self::ADDRESS, $postString);
    $job = json_decode($job, true);

    $resultString = $this->getJobQueryParam( $job['id'] );

    if (php_sapi_name() === 'cli') {
      echo 'job id: ' . $job['id'] . "\n";
    }

    do {
      if (php_sapi_name() === 'cli') {
        echo 'waiting for: ' . $job['id'] . "\n";
      }
      sleep(self::READ_TIME);
      $appString = $this->query( $resultString );
    } while (trim($appString) == "");

    return $this->parse( $appString );
  }

  public function queryResult($job_id) {
    $param = $this->getJobQueryParam($job_id);
    $result = $this->query($param);
    return $this->parse($result);
  }

  /**
   * @param string $job_id
   *
   * @return string
   */
  private function getJobQueryParam( $job_id ) {
    return 'key=' . KEPLER_KEY . '&do=query&id=' . $job_id . '&email=' . Admin::DEVELOPER_CS_MAIL;
  }

  /**
   * 处理 Kepler 查询返回的结果集
   *
   * @param $appString
   *
   * @return array
   */
  private function parse( $appString ) {
    $apps    = explode( "\n", $appString );
    $ziDuans = explode( "\t", $apps[0] );
    $sizes   = count($ziDuans);

    $list = array();
    for ( $i = 1, $len = count( $apps ) - 1; $i < $len; $i ++ ) {
      $row    = trim($apps[ $i ]);
      if (!$row) {
        continue;
      }
      $values = explode( "\t", $row );
      $values = count($values) == $sizes ? $values : array_merge($values, ['']);
      $app    = array_combine( $ziDuans, $values );
      array_push( $list, $app );
    }

    return $list;
  }

  /**
   * @param $resultString
   *
   * @return mixed
   */
  private function query( $resultString ) {
    $result    = Utils::request_by_curl( self::ADDRESS, $resultString );
    $result    = json_decode( $result, true );
    $appString = $result['progress'] === 100 ? $result['result'] : '';

    return $appString;
  }
}