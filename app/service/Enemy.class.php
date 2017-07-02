<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 15/7/16
 * Time: 下午2:07
 */

namespace diy\service;


use SQLHelper;

class Enemy extends Base {
  static $TABLE = 't_apk_spy';
  const HAS = 1;

  public function log_sm($id, $url, $pack_name, $app_name) {
    // 记到表里
    $attr = [
      'ad_id' => $id,
      'url' => $url,
      'user' => $_SESSION['id'],
      'sm' => self::HAS,
    ];
    $DB = $this->get_write_pdo();
    $count = SQLHelper::insert($DB, self::$TABLE, $attr, true);

    $admin = new Admin();
    $me = $admin->get_user_info(['id' => $_SESSION['id']]);
    $me = array_values($me)[0];
    $attr['name'] = $me;

    // 给相关人发邮件
    $mailer = new Mailer();
    $to = [
      'chris.ji@dianjoy.com',
      'hui.wang@dianjoy.com',
      'chenjie.zhou@dianjoy.com',
      'lujia.zhai@dianjoy.com',
    ];
    $subject = '【点乐自助平台】监测到包含数盟SDK的包上传';
    $content = $mailer->create('spy-sm', array_merge($attr, array(
      'pack_name' => $pack_name,
      'app_name' => $app_name,
    )));
    $check = $mailer->send($to, $subject, $content);

    return $count && $check;
  }
}