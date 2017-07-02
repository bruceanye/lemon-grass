<?php
/**
 * Created by PhpStorm.
 * User: 路佳
 * Date: 2015/2/6
 * Time: 17:17
 */

namespace diy\utils;

use RecursiveArrayIterator;
use RecursiveIteratorIterator;
use DateTime;
use DatePeriod;
use DateInterval;

class Utils {
  /**
   * 从一个数组中择出来需要的
   *
   * @param $array
   *
   * @return array
   */
  public static function array_pick($array) {
    if (!is_array( $array )) {
      return $array;
    }
    $keys = array_slice(func_get_args(), 1);
    $keys = self::array_flatten($keys);
    $pick = array();
    foreach ( $keys as $key ) {
      if (!array_key_exists($key, $array)) {
        continue;
      }
      $pick[$key] = $array[$key];
    }
    return $pick;
  }

  public static function array_omit($array) {
    if (!is_array( $array )) {
      return $array;
    }
    $keys = array_slice(func_get_args(), 1);
    $keys = self::array_flatten($keys);
    $pick = array();
    foreach ( $array as $key => $value ) {
      if (in_array($key, $keys, true)) {
        continue;
      }
      $pick[$key] = $value;
    }
    return $pick;
  }

  public static function array_flatten($array){
    return iterator_to_array(new RecursiveIteratorIterator(new RecursiveArrayIterator($array)), false);
  }

  /**
   * 以递归的形式遍历一个数组，审查每一个对象
   * @param $array
   * @return array
   */
  public static function array_strip_tags($array) {
    $result = array();

    foreach ( $array as $key => $value ) {
      $key = strip_tags($key);

      if (is_array($value)) {
        $result[$key] = self::array_strip_tags($value);
      } else if (is_numeric($value) && !preg_match('/^0\d+$/', $value) && $value < PHP_INT_MAX) {
        $result[$key] = $value + 0;
      } else {
        $result[$key] = htmlspecialchars(trim(strip_tags($value)), ENT_QUOTES | ENT_HTML5);
      }
    }

    return $result;
  }

  /**
   * 遍历一个数组,检查其中的元素是否都符合检查函数的要求
   *
   * @param array $result
   * @param callable $callable
   *
   * @return bool
   */
  public static function array_all( array $result, callable $callable ) {
    foreach ( $result as $value ) {
      $check = $callable($value);
      if (!$check) {
        return $check;
      }
    }
    return true;
  }

  /**
   * 对数组进行排序
   *
   * @param array $array 目标数组
   * @param array|string $order 排序的键值
   * @param string $seq OPTIONAL 正序/倒序
   *
   * @return array
   */
  public static function array_sort(array $array, $order, $seq = '') {
    if (!$order) {
      return $array;
    }
    if (is_array($order)) {
      $key = array_keys($order)[0];
      $seq = $order[$key];
      $order = $key;
    }
    $seq = strtoupper($seq) == 'DESC' ? -1 : 1;
    usort( $array, function ($a, $b) use ($order, $seq) {
      $a = $a[$order];
      $b = $b[$order];
      $diff = is_string($a) && is_string($b) ? strcmp($a, $b) : $a - $b;
      return $seq * ceil($diff);
    });
    return $array;
  }

  /**
   * @deprecated
   * @param $array
   * @param $order
   * @param $seq
   *
   * @return mixed
   */
  public static function array_order(array $array, $order, $seq = '') {
    return self::array_sort( $array, $order, $seq );
  }

  public static function create_id() {
    return md5(uniqid());
  }

  public static function format_file_size ($size) {
    $units = array('B', 'KB', 'MB', 'GB');

    if ($size > 0) {
      $unit = intval(log($size, 1024));

      if (array_key_exists($unit, $units)) {
        return round($size / pow(1024, $unit), 2) . $units[$unit];
      }
    }

    return $size;
  }

  /**
   * 取客户端ip
   * @return string
   */
  public static function get_client_ip() {
    if ($_SERVER['HTTP_CLIENT_IP']) {
      $ip = $_SERVER['HTTP_CLIENT_IP'];
    } else if ($_SERVER['HTTP_X_FORWARDED_FOR']) {
      $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else if ($_SERVER['HTTP_X_FORWARDED']) {
      $ip = $_SERVER['HTTP_X_FORWARDED'];
    } else if ($_SERVER['HTTP_FORWARDED_FOR']) {
      $ip = $_SERVER['HTTP_FORWARDED_FOR'];
    } else if ($_SERVER['HTTP_FORWARDED']) {
      $ip = $_SERVER['HTTP_FORWARDED'];
    } else if ($_SERVER['REMOTE_ADDR']) {
      $ip = $_SERVER['REMOTE_ADDR'];
    } else {
      $ip = 'UNKNOWN';
    }
    return $ip;
  }

  /**
   * 计算两个日期的差值,返回天数
   * @param $date1
   * @param $date2
   * @return float
   */
  public static function calculate_date($date1, $date2) {
    $days = round((strtotime($date1)-strtotime($date2)) / 86400);
    return $days;
  }

  /**
   * @param string $seq
   * @param string $defaults
   *
   * @return string
   */
  public static function get_seq($seq, $defaults = 'DESC') {
    return in_array( strtoupper( $seq ), [ 'DESC', 'ASC' ] ) ? $seq : $defaults;
  }

  /**
   * @param string $key
   *
   * @return mixed
   */
  public static function is_field($key) {
    return preg_match( '/^[\w\d_]+$/', $key ) === 1;
  }

  /**
   * 判断一个数组是不是只有数字索引
   * 因为很多时候是配合js,所以直接判断最后一个键是不是等于最大长度-1就行了
   * 
   * @param array $array
   *
   * @return bool
   */
  public static function isSequentialArray(array $array) {
    if (is_array($array) && !$array) { // 接受空数组
      return true;
    }
    $keys = array_keys($array);
    return array_pop($keys) === count($array) - 1;
  }

  /**
   * 通过curl来请求url
   * @param $remote_server
   * @param $post_string
   * @return mixed
   */
  public static function request_by_curl ($remote_server, $post_string) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $remote_server);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $data = curl_exec($ch);
    curl_close($ch);

    return $data;
  }

  /**
   * 分割一段时间,组成新的格式形式:[$before~$after, $before2~$after2]
   * @param $start
   * @param $end
   * @param $rejectDates
   * @return array
   */
  public static function splitDates($start, $end, $rejectDates) {
    $rejectDates = $rejectDates ? $rejectDates : [];
    $start = new DateTime($start);
    $end = new DateTime($end);
    $end = $end->modify('+1 day');

    $per = new DateInterval('P1D');
    $dateRanger = new DatePeriod($start, $per, $end);

    $historyDates = [];
    foreach ($dateRanger as $date) {
      $historyDates[] = $date->format('Y-m-d');
    }

    $historyDates = array_merge(array_diff($historyDates, $rejectDates), []); // 数组下标重排

    $result = [];
    if (count($historyDates) == 1) {
      $result = $historyDates;
    } else {
      for ($i = 0, $len = count($historyDates); $i < $len;) {
        $per = 1;
        for ($j = $i + 1; $j < count($historyDates); $j++) {
          if (strtotime('+' . $per . ' day', strtotime($historyDates[$i])) != strtotime($historyDates[$j])) {
            $beforeDate = $historyDates[$i];
            $afterDate = $historyDates[$j-1];
            $result[] = ($beforeDate == $afterDate) ? $beforeDate : ($beforeDate . '~' . $afterDate);
            $i = $j;
            break;
          } else {
            if ($j == count($historyDates) - 1) {
              $result[] = $historyDates[$i] . '~' . $historyDates[$j];
              $i = $j + 1;
              break;
            }
          }
          $per++;
        }
      }
    }
    return $result;
  }
}