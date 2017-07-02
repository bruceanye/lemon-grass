<?php
/**
 * Created by PhpStorm.
 * Date: 2015/3/20
 * Time: 0:00
 * @overview
 * @author Meatill <lujia.zhai@dianjoy.com>
 * @since
 */

namespace diy\model;

use diy\error\Error;
use diy\exception\ADException;
use diy\service\AD;
use diy\service\Admin;
use diy\service\ADOperationLogger;
use diy\service\Auth;
use diy\service\Location;
use diy\utils\Utils;
use SQLHelper;

/**
 * @property int agreement_id
 * @property int ad_app_type
 * @property int quote_rmb
 */
class ADModel extends Base {
  static $FIELDS_CALLBACK = array('salt', 'click_url', 'ip');
  static $FIELDS_IOS = array('put_jb', 'put_ipad', 'salt', 'click_url', 'ip',
    'url_type', 'corp', 'http_param', 'process_name', 'down_type', 'rank', 'callback_reqs',
    'open_url_type', 'search_flag', 'keywords', 'http_method', 'install_notify_permit',
    'requirements', 'sign_formula', 'sign_salt', 'success_flag', 'itunes_id');
  static $FIELDS_CHANNEL = array('channel', 'agreement_id', 'owner', 'cid', 'url', 'user',
    'pwd', 'feedback', 'cycle', 'execute_owner', 'advertiser_tag');
  static $FIELDS_DIY = ['start_time', 'end_time', 'total_num'];
  static $FIELDS_OMIT = array('file-email', 'permission', 'provinces', 'replace',
    'replace-with', 'replace-time', 'put_provinces');
  static $FIELDS_PROMOTIONS = array('hot', 'limit', 'cate_other', 'banner_img', 'private', 'tag_num');
  static $FIELDS_CPC = array('cpc_img', 'img_md5', 'top', 'position', 'click_bath', 'click_step_rmb', 'click_quote_rmb');
  static $FIELDS_SHARE = array('invite_content', 'introduction_title', 'introduction_content', 'share_title', 'share_content',
    'reward_content', 'mode_a', 'mode_b', 'show_ratio', 'redirect_url', 'delta_second', 'combo_second', 'combo_rmb', 'mode_c');
  static $FIELDS_COVERALL = array('ad_name', 'ad_type', 'ad_text', 'ad_desc', 'pic_path', 'ad_shoot', 'cate', 'setup_tips',
    'before_tips', 'share_text', 'active_time', 'note_pay');
  static $FIELDS_IMG = [ 'ad_url', 'ad_shoot', 'pic_path', 'lock_img' ];
  static $T_CALLBACK = 't_adinfo_callback';
  static $T_IOS_INFO = 't_adinfo_ios';
  static $T_SOURCE = 't_ad_source';
  static $T_INFO = 't_adinfo';
  static $T_RMB = 't_adinfo_rmb';
  static $T_DIY = 't_adinfo_diy';
  static $T_COMMENT = 't_diy_ad_comment';
  static $COMMENT_STATUS = ['新建', '处理完', '删除'];
  static $CATE = ['试用', '注册', '深度任务', '申请', '申请成功', '打开', '关注', '礼包', '抽奖',
    '抢购', 'Launch', '折扣', '精品', '其它'];
  static $FEEDBACK = array('', '截图', '官方后台', '渠道后台', 'API接口', '核对mac地址',
    '实时数据', '按点乐数据结算');
  static $CYCLE = array('隔日数据', '一周两次数据', '隔周数据', '隔月数据');

  const DELETED = -2;
  const ONLINE = 0;
  const OFFLINE = 1;
  const APPLY = 2;
  const REPLACE = 3;
  const REJECTED = 4;
  const DRAFT = 5;

  const ANDROID = 1;
  const IOS = 2;

  const IDFA_TRANSFER = 0;
  const IDFA_QUOTE = 3;

  public $replace;


  public function save(array $attr = null) {
    // 拆分不同表的数据
    list(
      $callback,
      $ios,
      $channel,
      $diy,
      $attr,
      $permissions,
      $provinces,
    ) = $this->split_attr($attr);
    $DB = $this->get_write_pdo();

    // 权限信息
    $this->save_permissions( $permissions );

    //广告投放地理位置信息
    $this->save_provinces( $provinces );

    // 插入广告信息
    $this->save_info( $DB, $attr );

    // 插入消费记录
    $this->save_rmb_info( $DB );

    // 记录平台专属数据
    if ($attr['ad_app_type'] == 2) {
      $this->save_ios_info( $DB, $ios );
    } else if ($callback['click_url']) { // 有回调再插入
      $this->save_android_info( $DB, $callback );
    }

    // 添加广告主后台信息.
    $this->save_source( $DB, $channel );

    // 记录CP信息
    if ($_SESSION['role'] == Auth::$CP_PERMISSION) {
      $this->save_diy_info($DB, $diy);
    }

    $this->log( false, $attr );
  }

  public function fetch() {
    $service = new AD();
    $attr = $service->get_ad_info(array('id' => $this->id), 0, 1);
    if ($attr['ad_sdk_type'] == 7 || $attr['cpc_cpa'] == 'cpc' || $attr['ad_sdk_type'] == 4) {
      $attr['put_level'] = '';
    }

    $this->attributes = $attr;
  }

  public function update( array $attr = null, $message = '' ) {
    $attr = $this->validate($attr);
    $this->changed = $attr;

    // 拆分不同表的数据
    list(
      $callback,
      $ios,
      $channel,
      $diy,
      $base,
      $permissions,
      $provinces,
      ) = $this->split_attr($attr, true);
    $DB = $this->get_write_pdo();
    $check = true;
    if ($base) {
      $check = SQLHelper::update($DB, self::$T_INFO, $base, $this->id);
    }
    if ($channel) {
      $check = SQLHelper::update($DB, self::$T_SOURCE, $channel, $this->id);
    }
    if ($ios) {
      $check = SQLHelper::update($DB, self::$T_IOS_INFO, $ios, $this->id);
    }
    if ($callback) {
      $check = SQLHelper::update($DB, self::$FIELDS_CALLBACK, $callback, $this->id);
    }
    if ($diy) {
      $check = SQLHelper::update($DB, self::$FIELDS_DIY, $diy, $this->id);
    }
    if (!$check) {
      throw new ADException('修改广告失败', 30);
    }

    // 权限信息
    $this->save_permissions( $permissions );

    //广告投放地理位置信息
    $this->save_provinces( $provinces );

    $this->log( true, $attr, $message );

    return $this->toJSON();
  }

  public function check_owner( ) {
    $service = new AD();
    return $service->check_ad_owner( $this->id );
  }

  public function save_ad_comment( $id, $comment ) {
    $DB = $this->get_write_pdo();
    $attr = array(
      'ad_id' => $id,
      'comment' => $comment,
      'author' => $_SESSION['id'],
      'create_time' => date('Y-m-d H:i:s'),
    );
    return SQLHelper::insert($DB, self::$T_COMMENT, $attr);
  }

  /**
   * 校验用户修改的内容
   * @param array $attr
   * @return array
   */
  protected function validate(array $attr = null) {
    $attr = parent::validate($attr);

    if ( array_key_exists('ad_text', $attr) && mb_strlen( $attr['ad_text'] ) > 45 ) {
      $this->error = new Error( 1, '广告语不能超过45个字符', 400 );
      return false;
    }

    // CP用户只能选择两个报价
    if ($_SESSION['role'] == Auth::$CP_PERMISSION && array_key_exists('quote_rmb', $attr) && $attr['quote_rmb'] != 300 && $attr['quote_rmb'] != 600) {
      $this->error = new Error(1, '广告单价错误', 406);
      return false;
    }

    // 去掉上传中的绝对路径
    foreach ( self::$FIELDS_IMG as $field ) {
      if ($attr[$field]) {
        $attr[$field] = str_replace(UPLOAD_URL, '', $attr[$field]);
      }
    }

    // 去掉没用的replace
    if (empty($attr['replace'])) {
      unset($attr['replace']);
    }

    // 对数据进行预处理
    if (isset($attr['net_type'])) {
      if (is_array($attr['net_type'])) {
        if (in_array(0, $attr['net_type'])) {
          $attr['net_type'] = 0;
        } else {
          $attr['net_type'] = implode(',', $attr['net_type']);
        }
      }
    }
    if ($attr['seq_rmb'] || $attr['step_rmb']) {
      $attr['seq_rmb'] = $attr['seq_rmb'] == '' ? (int)$attr['step_rmb'] : (int)$attr['seq_rmb'];
    }
    if (isset($attr['message'])) {
      $attr['others'] = $attr['message'];
      unset($attr['message']);
    }

    // 新增了一个 `show_title` 字段
    if (array_key_exists('ad_name', $attr)) {
      $attr['show_title'] = $attr['ad_name'];
    }

    return $attr;
  }

  private function split_attr(array $attr = null, $only_split = false) {
    $attr = $attr ? $attr : $this->attributes;

    $callback = Utils::array_pick($attr, self::$FIELDS_CALLBACK);
    $ios = Utils::array_pick($attr, self::$FIELDS_IOS);
    $channel = Utils::array_pick($attr, self::$FIELDS_CHANNEL);
    $diy = Utils::array_pick($attr, self::$FIELDS_DIY);
    $permissions = $attr['permission'];
    $provinces = $attr['provinces'] ? $attr['provinces'] : $attr['put_provinces'];

    if (!$only_split) {
      $me = $_SESSION['id'];
      $im_cp = $_SESSION['role'] == Auth::$CP_PERMISSION;
      $now = date('Y-m-d H:i:s');
      $callback['ad_id'] = $channel['id'] = $ios['ad_id'] = $diy['id'] = $this->id;
      $attr['status'] = 2; // 新建，待审核
      $attr['ad_sdk_type'] = 1; // 只允许广告墙
      $attr['create_user'] = $me;
      $attr['create_time'] = $now;
      if ($im_cp) {
        $channel['feedback'] = 7;
        $channel['cycle'] = 1;
        $attr['ad_app_type'] = 2;
        $attr['cate'] = 1;
      } else {
        if (!$channel['execute_owner']) {
          $channel['execute_owner'] = $me;
        }
        if (!$channel['owner']) {
          $channel['owner'] = $me;
        }
      }

      if ($attr['replace']) {
        $this->replace = $attr['replace-with'];
        $attr['status'] = 3; // 欲替换之前的广告
        $attr['status_time'] = $attr['replace-time'];
      }
    }

    $attr = Utils::array_omit($attr, self::$FIELDS_CALLBACK, self::$FIELDS_CHANNEL, self::$FIELDS_IOS, self::$FIELDS_DIY, self::$FIELDS_OMIT);
    if ($only_split) {
      unset($attr['id']);
    }

    return [$callback, $ios, $channel, $diy, $attr, $permissions, $provinces];
  }

  /**
   * @param $permissions
   *
   * @throws ADException
   */
  protected function save_permissions( $permissions ) {
    // 保存权限数据
    if ( $permissions ) {
      $service = new AD();
      $check = $service->set_permissions( $this->id, $permissions );
      if (!$check) {
        throw new ADException('插入应用权限信息失败', 26);
      }
    }
  }

  /**
   * @param $provinces
   *
   * @return array
   * @throws ADException
   */
  protected function save_provinces( $provinces ) {
    if ( $this->get('province_type') == 1 && isset($provinces) ) {
      $location = new Location();
      if ( ! is_array( $provinces ) ) {
        $provinces = array( (int) $provinces );
      }

      $location->del_by_ad($this->id);
      $check = $location->insert_ad_province( $this->id, $provinces );
      if (!$check) {
        throw new ADException('插入投放地理位置失败', 21);
      }
    }
  }

  /**
   * @param $DB
   * @param $attr
   *
   * @return bool
   * @throws ADException
   */
  protected function save_info( $DB, $attr ) {
    $check = SQLHelper::insert( $DB, self::$T_INFO, $attr );
    if ( ! $check ) {
      $e        = new ADException( '插入广告失败', 20 );
      $e->debug = SQLHelper::$info;
      throw $e;
    }

    return $check;
  }

  /**
   * @param $DB
   *
   * @return bool
   * @throws ADException
   */
  protected function save_rmb_info( $DB ) {
    $rmb   = array(
      'id'      => $this->id,
      'rmb'     => 0,
      'rmb_in'  => 0,
      'rmb_out' => 0,
    );
    $check = SQLHelper::insert( $DB, self::$T_RMB, $rmb );
    if ( ! $check ) {
      $e        = new ADException( '插入消费记录失败', 25 );
      $e->debug = SQLHelper::$info;
      throw $e;
    }

    return $check;
  }

  private function save_diy_info( $DB, $diy ) {
    if (count($diy) <= 1) {
      return false;
    }
    $check = SQLHelper::insert($DB, self::$T_DIY, $diy);
    if (!$check) {
      $e = new ADException('插入DIY专属信息失败', 27);
      $e->debug = SQLHelper::$info;
      throw $e;
    }
    return $check;
  }

  /**
   * @param $DB
   * @param $ios
   *
   * @return bool
   * @throws ADException
   */
  protected function save_ios_info( $DB, $ios ) {
    $check = SQLHelper::insert( $DB, self::$T_IOS_INFO, $ios );
    if ( ! $check ) {
      $e        = new ADException( '插入iOS专属数据失败', 22 );
      $e->debug = SQLHelper::$info;
      throw $e;
    }

    return $check;
  }

  /**
   * @param $DB
   * @param $callback
   *
   * @return bool
   * @throws ADException
   */
  protected function save_android_info( $DB, $callback ) {
    $check = SQLHelper::insert( $DB, self::$T_CALLBACK, $callback );
    if ( ! $check ) {
      $e        = new ADException( '插入Android回调信息失败', 23 );
      $e->debug = SQLHelper::$info;
      throw $e;
    }

    return $check;
  }

  /**
   * @param $DB
   * @param $channel
   *
   * @throws ADException
   */
  protected function save_source( $DB, $channel ) {
    $check = SQLHelper::insert( $DB, self::$T_SOURCE, $channel );
    if ( ! $check ) {
      $e        = new ADException( '插入广告主后台信息失败', 24 );
      $e->debug = SQLHelper::$info;
      throw $e;
    }
  }

  /**
   * @param $DB
   * @param $promotions
   *
   * @return bool
   * @throws ADException
   */
  protected function save_promotions( $DB, $promotions ) {
    $check = SQLHelper::insert( $DB, 't_adinfo_promotions', $promotions );
    if ( ! $check ) {
      $e        = new ADException( '插入特惠广告信息失败', 28 );
      $e->debug = SQLHelper::$info;
      throw $e;
    }

    return $check;
  }

  /**
   * @param $DB
   * @param $cpc
   *
   * @return bool
   * @throws ADException
   */
  protected function save_cpc( $DB, $cpc ) {
    $check = SQLHelper::insert( $DB, 't_adinfo_click', $cpc );
    if ( ! $check ) {
      $e        = new ADException( '插入CPC广告信息失败', 29 );
      $e->debug = SQLHelper::$info;
      throw $e;
    }

    return $check;
  }

  /**
   * @param $DB
   * @param $share
   *
   * @return bool
   * @throws ADException
   */
  protected function save_share( $DB, $share ) {
    $check = SQLHelper::insert( $DB, 't_adinfo_share', $share );
    if ( ! $check ) {
      $e        = new ADException( '插入分享广告信息失败', 30 );
      $e->debug = SQLHelper::$info;
      throw $e;
    }

    return $check;
  }

  /**
   * 记录操作
   *
   * @param $is_edit
   * @param $attr
   * @param string $message
   */
  private function log( $is_edit, $attr, $message = '' ) {
    $logger = new ADOperationLogger();
    unset($attr['id']);
    if ($is_edit) {
      // 为避免大量中文被转码占空间，先把备注取出来，json_encode完再放回去
      $others = '';
      if ( $attr['others'] ) {
        $others         = $attr['others'];
        $attr['others'] = '{{others}}';
      }
      $comment = json_encode( $attr );
      if ( $others ) {
        $comment        = str_replace( '{{others}}', $others, $comment );
        $attr['others'] = $others;
      }
      $logger->log( $this->id, 'ad', 'edit', "{$comment}\n[{$message}]");
    } else {
      $logger->log($this->id, 'ad', 'add', $attr['others'], 0);
    }
  }

  public function toJSON( array $attr = null) {
    $attr = $attr ? $attr : parent::toJSON();
    if ($attr['owner']) {
      $admin = new Admin();
      $ad_info['owner_name'] = $admin->get_user_info_by_id($attr['owner'])['NAME'];
    }
    if ($attr['agreement_id']) {
      $agreement = new AgreementModel(['id' => $attr['agreement_id']]);
      $agreement->fetch();
      $attr['channel'] = $agreement->company_short;
      $agreementChangeLog = new AgreementChangeLogCollection(['ad_id' => $this->id]);
      $agreementChangeLog->fetch();
      $attr['other_agreements'] = $agreementChangeLog->toJSON();
    }
    if (Auth::is_cp()) {
      $attr['status'] = $attr['num'] <= 0 ? ADModel::OFFLINE : $attr['status'];
    }
    foreach ( self::$FIELDS_IMG as $field ) {
      if (!array_key_exists($field, $attr)) {
        continue;
      }
      if ($field == 'ad_shoot') {
        $attr['ad_shoot'] = array_map(function ($path) {
          return $this->createCompletePath($path);
        }, preg_split('/,+/', $attr['ad_shoot']));
        continue;
      }
      $attr[$field] = $this->createCompletePath($attr[$field]);
    }
    return $attr;
  }

  private function createCompletePath( $path ) {
    return preg_match('~^(https?:)?//~', $path) ? $path : UPLOAD_URL . $path;
  }
}