<?php
use diy\utils\Utils;

/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 16/7/27
 * Time: 下午7:04
 */
class AdminLogger {
  const METHODS = ['GET', 'POST', 'PATCH', 'DELETE', 'OPTIONS'];
  const V5 = 1;
  const V3 = 2;
  const DIY = 3;
  const EXCLUSIVE = '~/notice/$~';

  public static function log($id, $from) {
      return;
    $url = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
    if (preg_match(self::EXCLUSIVE, $url)) {
      return;
    }
    $DB = require PROJECT_DIR . '/app/connector/pdo_admin_log.php';
    $id = $id ? $id : 0;
    $date = date('Y-m-d');
    $table = "t_admin_log_{$date}";
    $method = array_search(strtoupper($_SERVER['REQUEST_METHOD']), self::METHODS) + 1;
    $check = SQLHelper::insert($DB, $table, [
      'admin_id' => $id,
      'ip' => Utils::get_client_ip(),
      'datetime' => date('Y-m-d H:i:s'),
      'method' => $method,
      'uri' => $url,
      'from' => $from,
    ]);
    if (!$check) {
      error_log(json_encode(SQLHelper::$info));
    }
  }
}