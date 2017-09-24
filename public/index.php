<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 14/11/12
 * Time: 下午5:20
 */


// Autoload
require '../vendor/autoload.php';
require '../config/config.php';

use diy\controller\BaseController;
use dianjoy\Macaw\Macaw;
use diy\service\Auth;

// 设置错误日志位置
ini_set('error_log', '/tmp/diy_' . date('md') . '.log');

// 以此文件的目录作为项目目录
define('PROJECT_DIR', dirname(__FILE__) . '/..');

session_start();
header('Access-Control-Allow-Origin: ' . BaseController::get_allow_origin());
header('X-FRAME-OPTIONS', 'DENY'); // 禁止以 iframe 加载

// routes
require '../router/routes.php';
require '../router/auth.php';
require '../router/options.php';
if (Auth::is_cp()) {
  require '../router/cp.php';
} else {
  require '../router/ad.php';
  require '../router/agreement.php';
  require '../router/stat.php';
  require '../router/notice.php';
  require '../router/channel.php';
  require '../router/invoice.php';
  require '../router/client.php';
  require '../router/j_channel.php';
  require '../router/j_ad.php';


  AdminLogger::log($_SESSION['id'], AdminLogger::DIY);
}


Macaw::dispatch();