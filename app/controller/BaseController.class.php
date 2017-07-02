<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 14/11/13
 * Time: 下午5:58
 */

namespace diy\controller;

use CM;
use diy\error\Error;
use Redis;
use PDO;

class BaseController {
  static $HTTP_CODE = array(
    400 => 'Bad Request',
    401 => 'Unauthorized',
    403 => 'Forbidden',
    408 => 'Request Timeout',
    422 => 'Unprocessable Entity',
  );

  protected $need_auth = true;
  protected $redis = null;

  const OUTPUT_JSON = 'json';
  const OUTPUT_HTML = 'html';
  const OUTPUT_TEXT = 'text';
  const OUTPUT_XML = 'xml';
  const OUTPUT_CSV = 'csv';

  public function __construct() {
    // 在这里校验用户身份
    if ($this->need_auth && $_SERVER['REQUEST_METHOD'] != 'OPTIONS') {
      if (!isset($_SESSION['id']) || !isset($_SESSION['role'])) {
        $this->exit_with_error(1, '登录失效', 401);
      }
    }
  }

  public static function get_allow_origin() {
    $origins = explode(',', ALLOW_ORIGIN);
    $from = $_SERVER['HTTP_ORIGIN'];
    if (in_array($from, $origins)) {
      return $from;
    }
    return 'null';
  }

  public function on_options() {
    header('Access-Control-Allow-Headers: accept, content-type');
    header('Access-Control-Allow-Methods: GET,PUT,POST,PATCH,DELETE');
    header('Content-type: application/JSON; charset=UTF-8');

    $this->output( [
      'code' => 0,
      'method' => 'options',
      'msg' => 'ready',
    ] );
  }

  /**
   * @return PDO
   */
  protected function get_pdo_read() {
    return require PROJECT_DIR . '/app/connector/pdo_slave.php';
  }

  /**
   * @return PDO
   */
  protected function get_pdo_write() {
    return require PROJECT_DIR . '/app/connector/pdo.php';
  }

  /**
   * @return Redis
   */
  protected function get_redis() {
    $this->redis = $this->redis ? $this->redis : require PROJECT_DIR . '/app/connector/redis.php';
    return $this->redis;
  }
  protected function get_post_data() {
    $request = file_get_contents('php://input');
    $data = json_decode($request, true);
    if (json_last_error() == JSON_ERROR_NONE) {
      return $data;
    }

    if (preg_match('~text/xml~', $_SERVER['CONTENT_TYPE'])) {
      return simplexml_load_string($request, 'SimpleXMLElement', LIBXML_NOCDATA);
    }

    parse_str($request, $data);
    return $data;
  }

  protected function exit_with_error($code, $msg = '', $http_code = 400, $debug = '') {
    if ($code instanceof Error) {
      $http_code = $code->http_code;
      $result = $code->to_array();
    } else {
      $result = array(
        'code' => $code,
        'msg' => $msg,
        'debug' => $debug,
      );
    }
    header("HTTP/1.1 $http_code " . self::$HTTP_CODE[$http_code]);
    header('Content-type: application/JSON; charset=UTF-8');

    if ($http_code === 401) { // 登录失效或未登录
      $result['me'] = array();
    }
    exit(json_encode($result));
  }
  protected function output($result, $type = self::OUTPUT_JSON, $filename = '') {
    switch ($type) {
      case self::OUTPUT_JSON:
        header('Content-type: application/JSON; charset=UTF-8');
        if ($result['code'] === 201) {
          header('HTTP/1.1 201 Created');
          $result['code'] = 0;
        }
        $result = json_encode($result);
        break;

      case self::OUTPUT_HTML:
        header('Content-type: text/html; charset=UTF-8');
        break;

      case self::OUTPUT_TEXT:
        header('Content-type: text/plain; charset=UTF-8');
        break;

      case self::OUTPUT_XML:
        header('Content-type: text/xml; charset=UTF-8');
        break;

      case self::OUTPUT_CSV:
        header("Content-type:text/csv");
        header("Content-Disposition:attachment;filename=" . $filename);
        header("Cache-Control:must-revalidate,post-check=0,pre-check=0");
        header("Expires:0");
        header("Pragma:public");
        $result = "\xEF\xBB\xBF" . $result; // 加上 BOM
        break;

      default:
        header('Content-type:' . $type);
    }
    exit($result);
  }
} 