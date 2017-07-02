<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 15/2/2
 * Time: 下午5:06
 */

namespace diy\service;

use diy\utils\Utils;
use PDO;

class Auth extends Base {
  static $CP_PERMISSION = 100;

  public $user;

  public static function is_cp() {
    return $_SESSION['role'] == Auth::$CP_PERMISSION;
  }

  public function validate($username, $password, $no_log = false) {
    if (filter_var($username, FILTER_VALIDATE_EMAIL)) {
      return $this->validate_cp($username, $password, $no_log);
    }
    $password = $this->encrypt( $username, $password );
    $pdo = $this->get_read_pdo();
    $sql = "SELECT a.`id`,`QQ`,`permission`,`NAME`,b.`associate`,b.`location`
            FROM `t_admin` a
              LEFT JOIN `t_sales` b ON a.`id`=b.`id`
            WHERE `username`=:username AND `password`=:password AND `status`=1";
    $state = $pdo->prepare($sql);
    $state->execute(array(
      ':username' => $username,
      ':password' => $password,
    ));
    $this->user = $user = $state->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
      return false;
    }
    if ($no_log) {
      return true;
    }

    // 记录用户信息
    $_SESSION['user'] = $username;
    $_SESSION['id'] = $user['id'];
    $_SESSION['role'] = $user['permission'];
    $_SESSION['fullname'] = $user['NAME'];
    $_SESSION['location'] = $user['location'];

    return true;
  }

  public function has_permission() {
    return in_array((int)$this->user['permission'], array(0, 1, 5, 6));
  }

  private function validate_cp( $email, $password, $no_log = false ) {
    $password = $this->encrypt($email, $password);
    $pdo = $this->get_read_pdo();
    $sql = "SELECT a.`id`,`balance`,`full_name`,`has_export`,`has_today`,`is_api`,`cate`,
              `settle_cycle`,`settle_type`,`last_login_time`, `last_login_ip`,a.`type`,
              b.`id` AS `channel_id`
            FROM `t_diy_user` a
              JOIN `t_channel_map` b ON a.`corp`=b.`id`
            WHERE `email`=:email AND `password`=:password AND a.`status`=0";
    $state = $pdo->prepare($sql);
    $state->execute(array(
      ':email' => $email,
      ':password' => $password,
    ));
    $this->user = $user = $state->fetch(PDO::FETCH_ASSOC);

    if ($no_log) {
      return !!$user;
    }

    // 记录这次登录
    $time = date('Y-m-d H:i:s');
    $ip = Utils::get_client_ip();
    $DB = $this->get_write_pdo();
    $success = $user ? 1 : 0;
    $sql = "INSERT INTO `t_diy_user_login_log`
            (`email`, `ip`, `time`, `success`)
            VALUES (:email, '$ip', '$time', $success)";
    $state = $DB->prepare($sql);
    $state->execute(['email' => $email]);

    if (!$user) {
      return false;
    }

    // 记录最后一次登录
    $me = $user['id'];
    $sql = "UPDATE `t_diy_user`
            SET `last_login_time`='$time', `last_login_ip`='$ip'
            WHERE `id`='$me'";
    $DB->exec($sql);

    // 记录到session
    $_SESSION['email'] = $email;
    $_SESSION['role'] = self::$CP_PERMISSION;
    $_SESSION = array_merge($_SESSION, Utils::array_pick($user, ['id', 'balance', 'channel_id', 'username']));
    $_SESSION['fullname'] = $user['full_name'];
    foreach ( [ 'has_export', 'has_today', 'cate', 'settle_cycle', 'settle_type', 'is_api', 'type' ] as $key ) {
      $_SESSION[$key] = (int)$user[$key];
    }
    $_SESSION['last_login'] = array(
      'time' => $user['last_login_time'],
      'ip' => $user['last_login_ip'],
    );

    return true;
  }

  /**
   * @param $username
   * @param $password
   *
   * @return string
   */
  public function encrypt( $username, $password ) {
    return md5( $password . $username . SALT );
  }
}