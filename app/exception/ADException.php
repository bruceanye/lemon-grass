<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 15/4/9
 * Time: 下午12:48
 */

namespace diy\exception;


use Exception;

class ADException extends Exception {
  public $debug = null;
  public $http_code = 400;

  function __construct($message = null, $code = 0, $http_code = 400, $info = '') {
    parent::__construct($message, $code);
    $this->http_code = $http_code;
    $this->debug = $info;
  }
}