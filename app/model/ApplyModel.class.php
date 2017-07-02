<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 15/6/19
 * Time: 下午5:59
 */

namespace diy\model;


use diy\exception\ADException;
use diy\service\AD;
use diy\service\Apply;
use diy\service\Mailer;
use diy\service\Notification;
use SQLHelper;
use Exception;

class ApplyModel extends Base {
  static $T_APPLY = 't_diy_apply';

  private $ad_id = '';
  private $replace_id = '';
  private $label = '替换新包';
  private $key = '';

  public function __construct() {
    $params = func_get_args();
    $len = count($params);
    if (method_exists($this, $f = '__construct' . $len)) {
      call_user_func_array(array($this, $f), $params);
    }
  }

  public function __construct2($id, $attr) {
    $this->id = $id;
    $this->attributes = $attr;
  }

  public function __construct3($ad_id, $attr, $autoSend) {
    $this->ad_id = $ad_id;
    $this->attributes = $this->validate( $attr );

    if ($autoSend) {
      $this->send();
    }
  }

  public function send() {
    $now = date('Y-m-d H:i:s');
    // 对同一属性的修改不能同时有多个
    $service = new Apply();
    if ($service->is_available_same_attr($this->ad_id, $this->key)) {
      if ($this->key == 'set_ad_url') {
        $apply = new Apply();
        $apply->update_ad_url($this->get($this->key), $this->ad_id);
        return;
      }
      throw new ADException('该属性上次修改申请还未审批，不能再次修改', 41, 400);
    }

    $DB = $this->get_write_pdo();
    $check = SQLHelper::insert($DB, self::$T_APPLY, $this->attributes);
    if (!$check) {
      throw new ADException('创建申请失败', 40, 403, SQLHelper::$info);
    }
    $this->id = SQLHelper::$lastInsertId;

    // 给运营发通知
    $notice = new Notification();
    $notice_status = $notice->send(array(
      'ad_id' => $this->ad_id,
      'uid' => $this->id,
      'alarm_type' => $this->replace_id ? Notification::$REPLACE_AD : Notification::$EDIT_AD,
      'create_time' => $now,
      'app_id' => $this->replace_id, // 用appid字段保存被替换的广告id
    ));

    // 给运营发邮件
    $service = new AD();
    $info = $service->get_ad_info(array('id' => $this->replace_id ? $this->replace_id : $this->ad_id), 0, 1);
    $mail = new Mailer();
    $subject = $this->replace_id ? '替换成新广告' : '广告属性修改';
    $template = $this->replace_id ? 'apply-replace': 'apply-new';
    $mail->send(OP_MAIL, $subject, $mail->create($template, array_merge((array)$info, array(
      'id' => $this->ad_id,
      'replace_id' => $this->replace_id,
      'label' => $this->label,
      'is_status' => $this->get('key') == 'set_status',
      'value' => $this->get('value'),
      'comment' => $this->get('send_msg'),
      'owner' => $_SESSION['fullname'],
    ))));

    header('HTTP/1.1 201 Created');
    return true;
  }

  public function update(array $attr = null) {
    $DB_write = $this->get_write_pdo();
    $attr = isset($attr) ? $attr : $this->attributes;

    $check = SQLHelper::update($DB_write, self::$T_APPLY, $attr, $this->id);
    if (!$check) {
      throw new ADException('修改申请失败', 41, 403, SQLHelper::$info);
    }
    return $attr;
  }

  /**
   * @param array $attr
   *
   * @return array
   */
  protected function validate( array $attr = null ) {
    $attr = parent::validate($attr);
    $now        = date( 'Y-m-d H:i:s' );
    $this->replace_id = isset( $attr['replace_id'] ) ? $attr['replace_id'] : '';
    $result       = [
      'userid'      => $_SESSION['id'],
      'adid'        => $this->ad_id,
      'create_time' => $now,
      'send_msg'    => trim( $attr['message'] ),
    ];

    // 取欲修改的属性和值
    $key   = '';
    $value = '';
    if ( isset( $attr['num'] ) ) { // 今日余量需转换成rmb
      $key   = 'set_rmb';
      $value = (int) $attr['num'];
      $this->label = '今日余量';
    }
    if ( isset( $attr['job_num'] ) ) { // 每日投放需要看是否同时修改今日余量
      if ( isset( $attr['rmb'] ) ) {
        $result['set_rmb'] = $attr['set_rmb'] = $attr['job_num'];
        unset( $attr['rmb'] );
      }
      $key   = 'set_job_num';
      $value = $attr['job_num'];
      $this->label = '每日限量';
    }
    if ( isset( $attr['status'] ) ) {
      if ( $attr['status-time'] ) {
        $result['handle_time'] = $attr['status-time'];
      }
      $key   = 'set_status';
      $value = $attr['status'];
      $this->label = '上/下线';
    }
    if ( isset( $attr['ad_url'] ) ) {
      $key   = 'set_ad_url';
      $value = str_replace( UPLOAD_URL, '', $attr['ad_url'] );
      $this->label = '替换包';
    }
    if ( isset( $attr['quote_rmb'] ) ) {
      $key   = 'set_quote_rmb';
      $value = $attr['quote_rmb'];
      $this->label = '报价';
    }
    if ( $key ) {
      $this->key = $key;
      $result[ $key ] = $value;
    }

    return $result;
  }
}