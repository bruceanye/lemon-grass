<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 15/8/13
 * Time: 下午4:54
 */

namespace diy\service;


use diy\model\ADModel;
use diy\model\AgreementModel;
use diy\model\ChannelModel;
use PDO;
use SQLHelper;

class Baobei_Mailer extends Mailer {
  protected $DB_write;

  public function __construct($debug = false) {
    $this->username = 'baobei@dianjoy.com';
    $this->password = BAOBEI_PASSWORD;
    $this->from = '点乐广告主邮件报备';
    parent::__construct($debug);
  }

  public function send($to, $subject, $content, $cc = null) {
    // 留日志
    $DB = $this->get_write_pdo();
    SQLHelper::insert($DB, 't_ad_baobei', array(
      'ad_id' => $content['id'],
      'to_email' => $to,
      'send_time' => date('Y-m-d H:i:s'),
    ));
    $content = $this->translate($content);
    $content['eid'] = SQLHelper::$lastInsertId;


    $template = $content['ad_app_type'] == 1 ? 'baobei' : 'baobei_ios';
    $content = $this->create($template, $content);
    return parent::send($to, $subject, $content, $cc);
  }

  /**
   * @param $attr
   */
  private function translate( $attr ) {
    $ad = new AD();
    $admin = new Admin();
    $sales = $attr['owner'] ? $attr['owner'] : $_SESSION['id'];
    $types = $ad->get_all_labels(PDO::FETCH_KEY_PAIR);
    $attr['code'] = md5($attr['id'] . BAOBEI_SALT);
    $attr['quote_rmb'] = number_format($attr['quote_rmb'] / 100, 2);
    $attr['ad_type'] = $types[$attr['ad_type']];
    $attr['cate'] = ADModel::$CATE[$attr['cate']];
    $permissions = $ad->get_permissions( [ 'ad_id' => $attr['id'] ] );
    $permissions = array_values($permissions);
    $attr['permissions'] = implode("\n<br>", $permissions);
    $attr['feedback'] = ADModel::$FEEDBACK[$attr['feedback']];
    $attr['sales'] = $admin->get_user_info_by_id( $sales )['username'];
    $attr['ad_desc'] = preg_replace('/<span style="color: rgb\(255, 0, 0\);">(.*?)<\/span>/', '', $attr['ad_desc']); // 过滤掉标红文字
    if (is_numeric($attr['channel'])) {
      $channel = new ChannelModel(['id' => $attr['channel']]);
      $channel->fetch();
      $attr['channel'] = $channel->alias;
    }
    if ($attr['agreement_id']) {
      $agreement = new AgreementModel(['id' => $attr['agreement_id']]);
      $agreement->fetch();
      $attr['agreement'] = $agreement->company_short;
    }
    return $attr;
  }
}