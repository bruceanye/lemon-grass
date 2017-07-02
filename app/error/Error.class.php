<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 15/4/9
 * Time: 下午4:46
 */

namespace diy\error;


class Error {
  public $code = 1;
  public $message = '';
  public $http_code = 400;
  public $debug = null;

  public function __construct($code, $message, $http_code, $debug = null) {
    $this->code = $code;
    $this->message = $message;
    $this->http_code = $http_code;
    $this->debug = $debug;
  }

  public function to_array() {
    return array(
      'code' => $this->code,
      'message' => $this->message,
      'http_code' => $this->http_code,
      'debug' => $this->debug,
    );
  }
}