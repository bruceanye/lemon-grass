<?php
/**
 * Created by PhpStorm.
 * User: hemengyuan
 * Date: 15/5/6
 * Time: 下午3:15
 */
require dirname(__FILE__) . '/../app/helper/Utils.class.php';

use diy\utils\Utils;

session_start();
$defaults = array(
  'role' => 5,
  'id' => 12,
  'user' => 'meathill',
  'permission' => array('ad','publisher_account','develop','account','app','stat_ad','stat_user','stat_sys','sys','agreement','happy_lock'),
  'upload' => true,
  'channel_id' => 1006,
  'type' => 0,
);

$sessions = Utils::array_pick($_REQUEST, array_keys($defaults));
$_SESSION = array_merge($defaults, $sessions);

echo 'ok';