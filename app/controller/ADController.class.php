<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 14/11/17
 * Time: 下午2:01
 */

namespace diy\controller;

use diy\exception\ADException;
use diy\model\ADAgreementCollection;
use diy\model\ADModel;
use diy\model\AgreementChangeLogModel;
use diy\model\AgreementModel;
use diy\model\ApplyModel;
use diy\model\ChannelModel;
use diy\service\AD;
use diy\service\ADOperationLogger;
use diy\service\Agreement;
use diy\service\Apply;
use diy\service\Auth;
use diy\service\Baobei_Mailer;
use diy\service\Comment;
use diy\service\FileLog;
use diy\service\Job;
use diy\service\Location;
use diy\service\Mailer;
use diy\service\Notification;
use diy\service\Admin;
use diy\service\Stat;
use diy\service\User;
use diy\utils\Utils;
use Elasticsearch\ClientBuilder;
use Exception;
use SQLHelper;

class ADController extends BaseController {
  const REDIS_PREFIX = 'ad_edit_';

  static $FIELDS_APPLY = array('num', 'job_num', 'ad_url', 'quote_rmb');

  /**
   * 导出广告深度任务的idfa
   *
   * @param $id
   * @param $filename
   * @param $start_date
   * @param $end_date
   * @param $type
   */
  public function export_task($id, $filename, $start_date, $end_date, $type) {
    $task_str = "date,IDFA\n";
    $service = new AD();
    $tasks = $service->get_ad_task_log($id, $start_date, $end_date, $type);
    foreach ($tasks as $task) {
      $idfa = $task['device_id'];
      $task_str .= date('Y-m-s H:i:s' , strtotime($task['transfer_time'])) . ",~" . $idfa . "\n";
    }
    $idfa_total = count($tasks);

    $filename .= 'idfa总数' . $idfa_total . '个.csv';
    $this->output($task_str, self::OUTPUT_CSV, $filename);
  }

  /**
   * 导出广告idfa
   * @param $id
   */
  public function export_idfa($id) {
    $type = $_REQUEST['type'];
    $start_date = $_REQUEST['start'];
    $end_date = $_REQUEST['end'];
    $next_date = date('Y-m-d', strtotime($_REQUEST['end']) + (24 * 60 * 60));

    $service = new AD();
    if(!$service->check_ad_owner($id)) {
      $this->exit_with_error('60', '不是您的广告，您不能导出该广告的手机串号数据', 403);
    }

    // 根据广告id查询广告相关信息
    $filters = array(
      'id' => $id
    );
    $ad = $service->get_ad_info($filters, 0, 1);
    $filename = $ad['ad_name'] . ' ' . $ad['channel'] . '+' . $ad['cid'] . ' ' . $start_date . '~' . $_REQUEST['end-date'] . ' ';

    // 判断是否导出深度任务
    if ($type === "1" || $type === "2") {
      $this->export_task($id, $filename, $start_date, $next_date, $type);
      return;
    }

    $ad_str = "date,IMEI\n";
    $ios_str = "date,IDFA,关键词\n";
    if ($ad['ad_app_type'] == 2) { // ios广告
      $ios_transfer = $service->get_ios_transfer_log($id, $start_date, $end_date, $type);

      if ($type == ADModel::IDFA_TRANSFER) {
        foreach ($ios_transfer as $transfer) {
          $idfa = $transfer['device_id'];
          $ios_str .= substr($transfer['transfer_time'], 0, 19) . ",~" . $idfa . "," . $transfer['search_key'] . "\n";
        }
      } else {
        $ios_str = "date,IDFA\n";
        foreach ($ios_transfer as $transfer) {
          $idfa = $transfer['device_id'];
          $ios_str .= substr($transfer['transfer_time'], 0, 19) . ",~" . $idfa . "\n";
        }
      }
      $filename .= 'imei总数' . count($ios_transfer) . '个.csv';
      $this->output($ios_str, self::OUTPUT_CSV, $filename);
    } else { // android广告
      $ad_transfer = $service->get_ad_transfer_log($id, $start_date, $next_date);
      foreach ($ad_transfer as $transfer) {
        $imei = $transfer['device_id'];
        $ad_str .= explode(' ', $transfer['transfer_time'])[0] . ",~" . $imei . "\n";
      }
      $filename .= 'imei总数' . count($ad_transfer) . '个.csv';
      $this->output($ad_str, self::OUTPUT_CSV, $filename);
    }
  }

  public function get_list_new() {
      $this->output([]);
  }

  /**
   * 取广告列表
   * @author Meathill
   * @since 0.1.0
   */
  public function get_list() {
    $service =  new AD();
    $job_service = new Job();
    $admin = new Admin();
    $me = $_SESSION['id'];

    $pagesize = isset($_REQUEST['pagesize']) ? (int)$_REQUEST['pagesize'] : 10;
    $page = isset($_REQUEST['page']) ? (int)$_REQUEST['page'] : 0;
    $page_start = $page * $pagesize;
    $order = isset($_REQUEST['order']) ? trim($_REQUEST['order']) : 'create_time';
    $seq = isset($_REQUEST['seq']) ? trim($_REQUEST['seq']) : 'DESC';
    $filters = Utils::array_pick($_REQUEST, ['status', 'keyword', 'pack_name', 'ad_name', 'channel', 'agreement_id']);
    $filters = array_merge([
      'status' => [0, 1, 2, 3, 4],
      'salesman' => $me,
    ], $filters);

    $ads = $service->get_ad_info($filters, $page_start, $pagesize, array($order => $seq));
    $total = $service->get_ad_number($filters);
    $ad_ids = array_unique(array_keys(array_filter($ads)));
    $ops = $admin->get_ad_ops($ad_ids);
    $users = array();
    $decline = array();
    foreach ( $ads as $id => $ad ) {
      $users[] = $ad['execute_owner'];
      if ($ad['status'] == ADModel::REJECTED) {
        $decline[] = $id;
      }
    }

    // 取商务名单
    $user_service = new Admin();
    $users = $user_service->get_user_info(array('id' => array_filter(array_unique($users))));

    // 取当前申请
    $apply = new Apply();
    $applies = $apply->get_list_by_id($ad_ids);
    $applies_by_ad = array();
    foreach ( $applies as $id => $apply ) {
      $adid = $apply['adid'];
      if (!is_array($applies_by_ad[$adid])) {
        $applies_by_ad[$adid] = array();
      }
      unset($apply['adid']);
      $apply = array_filter($apply, function ($value) {
        return isset($value);
      });
      // 同时有每日限量和今日余量说明是要修改每日限量
      if (array_key_exists('set_job_num', $apply) && array_key_exists('set_rmb', $apply)) {
        unset($apply['set_rmb']);
      }
      $key = array_keys($apply)[0]; // 因为过滤掉了没有内容的键，又删掉了adid，只剩下要操作的key了
      $apply[$key . '_id'] = $id;
      $applies_by_ad[$adid][] = $apply;
    }

    // 取计划任务
    $ad_jobs = $job_service->get_ad_daily_job($ad_ids);

    // 取上下线计划任务
    $on_off_jobs = $job_service->get_ad_on_off_job($ad_ids);

    // 取被拒绝的广告的附言
    $decline = array_unique(array_filter($decline));
    $declines = null;
    if ($decline) {
      $comment_service = new Comment();
      $declines = $comment_service->get_comment(array(
        'ad_id' => $decline,
        'pack_name' => '',
      ));
    }

    // 获取备注记录
    $comments = $service->get_ad_comments($ad_ids);

    $result = array();
    foreach ($ads as $id => $ad) {
      $apply = array();
      if (is_array($applies_by_ad[$id])) {
        foreach ( $applies_by_ad[$id] as $item ) {
          $apply = array_merge($apply, $item);
        }
      }
      $decline = (array)$declines[$id];
      $job_num = array_key_exists($id, $ad_jobs) ? $ad_jobs[$id]['jobnum'] : 0;
      $on_off = $on_off_jobs[$id];

      $result[] = array_merge($ad, $apply, array(
        'id' => $id,
        'status' => (int)$ad['status'],
        'execute_owner' => $users[$ad['execute_owner']],
        'job_num' => $job_num,
        'job_time' => substr($ad_jobs[$id]['jobtime'], 11, 5),
        'reject' => $decline,
        'cm_others' => $comments[$id],
        'op' => $ops[$id],
        'search_flag' => (int)$ad['search_flag'],
        'on_off' => $on_off,
      ));
    }

    $this->output(array(
      'code' => 0,
      'msg' => 'get',
      'total' => $total,
      'list' => $result,
    ));
  }

  /**
   * 取广告列表，只取adinfo的内容
   */
  public function get_list_basic() {
    $service = new AD();
    $filters = array(
      'pack_name' => $_REQUEST['pack_name'],
      'ad_name' => $_REQUEST['ad_name'],
      'status' => ADModel::ONLINE,
      'ad_app_type' => 1,
      'ad_sdk_type' => 1,
    );

    if (empty($_REQUEST['pack_name']) && empty($_REQUEST['ad_name'])) {
      $this->exit_with_error(10, '参数错误', 400);
    }

    $order = array(
      'status' => 'asc',
      'create_time' => 'desc',
    );
    $result = $service->get_ad_info($filters, 0, 10, $order, '');
    foreach ( $result as $key => $ad ) {
      $ad['id'] = $key;
    }

    $this->output(array(
      'code' => 0,
      'msg' => 'get',
      'list' => array_values($result),
    ));
  }

  /**
   * 取某个广告的上传记录
   * @param $id
   */
  public function get_upload_history($id) {
    $ad = new AD();
    if (!$ad->check_ad_owner($id)) {
      $this->exit_with_error(1, '这不是您的广告，您无法查看它的上传记录', 403);
    }

    $service = new FileLog();
    $history = $service->get_ad_history($id);
    foreach ( $history as &$log ) {
      $log['url'] = UPLOAD_URL . $log['url'];
    }

    $this->output(array(
      'code' => 0,
      'msg' => 'get',
      'list' => $history
    ));
  }

  /**
   * 取新建广告的表单项，修改广告时当前内容
   * @author Meathill
   * @since 0.1.0
   * @param $id
   */
  public function init($id) {
    $service = new AD();
    $admin = new Admin();

    $labels = $service->get_all_labels();
    $permissions = $service->get_all_permissions();
    $me = $_SESSION['id'];
    $init = array(
      'ad_app_type' => 1, // 默认为 Android
      'ad_type' => 0,
      'cate' => 1,
      'cpc_cpa' => 'cpa',
      'put_level' => 3,
      'imsi' => 0,
      'put_net' => 0,
      'net_type' => 0,
      'put_jb' => 0,
      'put_ipad' => 0,
      'salt' => substr(md5(time()), 0, 8),
      'url_type'=>'',
      'province_type' => 0,
      'share_text'=>'',
      'down_type' => 0,
    );
    $options = array(
      'cates' => array('试用', '注册'),
      'net_types' => array( '全部', '移动', '联通', '电信' ),
      'channel_types' => ChannelModel::$TYPE,
      'ad_types' => $labels,
      'permissions' => $permissions,
      'provinces' => Location::$PROVINCES,
      'sales' => false,
    );

    $agreement = new Agreement();
    $init = array_merge($init, array(
      'ratio' => 1,
      'feedback' => '',
      'cycle' => '',
    ));

    $sales = $admin->get_sales_by_me($me);
    $relative_sales[$me] = $_SESSION['fullname'];
    foreach ($sales as $key => $sale) {
      $relative_sales[$key] = $sale;
    }

    if ($relative_sales) {
      $relative = array();
      foreach ($relative_sales as $key => $value) {
        $relative[] = array(
          'key' => $key,
          'value' => $value
        );
      }
      $options['sales'] = $options["relativeSales"] = $relative;
    }
    $options['agreements'] = $agreement->get_my_agreement(['today' => date('Y-m-d')]);

    if ($id === 'init') {
      $this->output(array(
        'code' => 0,
        'msg' => 'init',
        'ad' => $init,
        'options' => $options,
      ));
    }

    // 广告内容
    if (!$service->check_ad_owner($id)) {
      $this->exit_with_error(20, '您无法查询此广告的详细信息', 403);
    }
    $res = $service->get_ad_info( [ 'id' => $id ], 0, 1);
    $ad_shoot = preg_replace('/^,|,$/', '', $res['ad_shoot']);
    $ad_shoots = preg_split('/,+/', $ad_shoot);
    if (is_array($ad_shoots)) {
      foreach ( $ad_shoots as $key => $ad_shoot ) {
        $ad_shoots[$key] = $this->createCompletePath($ad_shoot);
      }
      $res['ad_shoot'] = $ad_shoots;
    }
    $res['ad_url'] = $this->createCompletePath($res['ad_url']);
    $res['pic_path'] = $this->createCompletePath($res['pic_path']);

    if (isset($_REQUEST['simple']) && $_REQUEST['simple'] == 0) {
      $this->output( [
        'code' => 0,
        'msg' => 'fetched',
        'ad' => $res,
      ] );
    }

    // 取计划任务，得投放量
    $job_service = new Job();
    $job = $job_service->get_ad_daily_job($id);
    $job = (array)$job[$id];

    // 省份
    if ($res['province_type'] == 1) {
      $location = new Location();
      $provinces = $location->get_provinces_by_ad($id);
      $res['put_provinces'] = array_values(array_unique($provinces));
    }

    // 被据广告读原因
    if ($res['status'] == 4) {
      $op_log = new ADOperationLogger();
      $log = $op_log->get_log(array('adid' => $id, 'type' => 'ad', 'action' => 'decline'));
      $res['decline'] = $log;
    }

    // 点评
    $res['comments'] = $service->get_comments(array('ad_id' => $id));
    
    // 关联的合同
    $agreements = new ADAgreementCollection(['ad_id' => $id]);
    $agreements->fetch();
    $res['link_agreements'] = $agreements->toJSON();

    // 今天的投放情况
    $stat = new Stat();
    $today = date('Y-m-d');
    $filter = [':ad_id' => $id, ':date' => $today];
    $clicks = $stat->get_ad_click_by_hour($filter);
    $transfer = $stat->get_ad_transfer_by_hour($filter);
    $today_stat = [];
    for ($hour = 0; $hour < 24; $hour++) {
      $item = array('hour' => $today . ' ' . $hour);
      if (isset($clicks[$hour])) {
        $item['click'] = (int)$clicks[$hour];
      }
      if (isset($transfer[$hour])) {
        $item['transfer'] = (int)$transfer[$hour];
      }
      $today_stat[] = $item;
    }

    if (in_array($res['status'], array(0, 1))) {
      // 备注
      $comments = $service->get_ad_comments(array($id));
      $res = array_merge($res, array(
        'cm_others' => $comments[$id],
      ));
    }

    $result = array_merge($init, $res, $job);
    $result['today_stat'] = $today_stat;

    $this->output(array(
      'code' => 0,
      'msg' => 'fetched',
      'ad' => $result,
      'options' => $options,
    ));
  }

  public function create_new() {

  }

  /**
   * 创建新广告
   * @author Meathill
   * @since 0.1.0
   * @param string $key
   */
  public function create($key) {
    if ($key != 'init') {
      $this->exit_with_error(10, '请求错误', 400);
    }
    $now = date('Y-m-d H:i:s');
    $attr = $this->get_post_data();
    $id = $attr['id'] = $attr['id'] ? $attr['id'] : Utils::create_id();

    $ad = new ADModel( $attr );
    if ($ad->error) {
      $this->exit_with_error($ad->error);
    }
    try {
      $result = $ad->save();
    } catch ( ADException $e) {
      $this->exit_with_error($e->getCode(), $e->getMessage(), 400, SQLHelper::$info);
    }

    $this->output($result);
    $client_email = $attr['file-email'];
    $replace_id = $ad->replace;

    // 给运营发新广告通知
    $notice = new Notification();
    $notice_status = $notice->send(array(
      'ad_id' => $id,
      'alarm_type' => Notification::$NEW_AD,
      'create_time' => $now,
    ));

    // 给运营发邮件
    $mail = new Mailer();
    $agreement = new AgreementModel(['id' => $attr['agreement_id']]);
    $agreement->fetch();
    $subject = "商务 [{$_SESSION['fullname']}] 创建新广告：{$attr['channel']} {$attr['ad_name']}";
    $mail->send(OP_MAIL, $subject, $mail->create('ad-new', $attr, array(
      'owner' => $_SESSION['fullname'],
      'cate' => ADModel::$CATE[$attr['cate']],
      'company_short' => $agreement->company_short,
    )));

    // 给广告主发送报备邮件
    if ($client_email) {
      $this->baobei($client_email, $attr);
    }

    // 给运营发修改申请
    if ($replace_id) {
      $this->send_apply($id, array(
        'replace_id' => $replace_id,
        'message'    => $attr['others'],
      ));
      return;
    }

    // log it
    $log = new ADOperationLogger();
    $log->log($id, 'ad', 'add', $attr['others'], 0);

    $result = array(
      'code'   => 0,
      'msg'    => '创建广告成功。相关通知' . ( $notice_status ? '已发' : '失败' ),
      'notice' => $notice_status ? '通知已发' : '通知失败',
      'ad'     => array(
        'id' => $id,
      ),
    );
    $this->output( $result );
  }

  /**
   * 修改广告
   * 部分属性的修改不会直接体现在表中，而是以请求的方式存在
   * 针对状态`status`、每日投放量`job_num`、今日余量`num`、广告链接`ad_url`，
   * 报价`quote_rmb`的修改会产生申请
   *
   * 其它修改会直接入库
   *
   * 暂时禁止CP用户修改报价
   *
   * @author Meathill
   * @since 0.1.0
   *
   * @param string $id 广告id
   * @param array [optional] $attr
   *
   * @return null
   */
  public function update($id, $attr = null) {
    $attr = $attr ? $attr : $this->get_post_data();
    $attr['id'] = $id;
    $ad = new ADModel($attr);
    $im_cp = $_SESSION['role'] == Auth::$CP_PERMISSION;
    $now = date('Y-m-d H:i:s');
    if ($ad->error) {
      $this->exit_with_error($ad->error);
    }
    $ad->fetch();

    if (!$ad->check_owner()) {
      $this->exit_with_error(10, '不是您的广告，您不能修改', 401);
    }
    if ($im_cp && array_diff_key($attr, ['id' => '', 'status' => 0])) {
      $this->exit_with_error(11, '您不能修改广告。', 401);
    }

    // 需要发申请的修改，只有未上线的需要申请
    $status = $ad->get('status');
    $to_status = null;
    $passed = [ ADModel::ONLINE, ADModel::OFFLINE ];
    if (array_intersect(self::$FIELDS_APPLY, array_keys($attr)) && in_array($status, $passed)) {
      $this->send_apply($id, $attr, $ad);
    }
    if (array_key_exists('status', $attr)) {
      $to_status = $attr['status'];
      if (!in_array($to_status, [ADModel::ONLINE, ADModel::OFFLINE, ADModel::DELETED])) {
        $this->exit_with_error(14, '您只能上下线或者删除广告', 403);
      }
      if ($status == ADModel::APPLY && in_array($to_status, $passed )) {
        $this->exit_with_error(12, '您的广告正在等待审核，不能申请上/下线。', 403);
      }
      if ($to_status == ADModel::DELETED && in_array($status, $passed )) {
        $this->exit_with_error(13, '您的广告已经通过审核，不能删除。', 403);
      }
      if ($status == ADModel::REJECTED && $to_status == ADModel::ONLINE) {
        $this->reapply_ad( $id, $attr, $ad );
      }
      if ($to_status == ADModel::ONLINE) { // 上线需要审批
        $this->send_apply($id, $attr, $ad);
      }
      // 大家可以自己控制下线，不过要给运营发通知
      if ($to_status == ADModel::OFFLINE) {
        $mail = new Mailer();
        $mail->send(OP_MAIL, '广告自助下线通知', $mail->create('ad-offline', $ad->toJSON(), [
          'message' => $attr['message'],
          'owner' => $_SESSION['fullname'],
          'status-time' => $attr['status-time'],
        ]));
      }
      $job = new Job();
      if ($attr['status-time'] && $attr['status-time'] > $now) { // 定时下线，创建计划任务
        $job->remove_on_off_job($id, $attr['status-time']);
        $job->create_job($id, Job::OFFLINE, Job::AT, $attr['status-time']);
        $this->output([
          'code' => 0,
          'msg' => '已添加计划任务，该广告届时将下线。',
          'ad' => [
            'status' => 0,
          ],
        ]);
      }
      $job->remove_all_job($id);
      $attr['status_time'] = date('Y-m-d H:i:s');
    }

    // 广告备注修改，需发送一枚通知
    if ($attr['comment']) {
      //设置备注
      $ad->save_ad_comment($id, $attr['comment']);
      $commentID = SQLHelper::$lastInsertId;

      $notice = new Notification();
      $check = $notice->send(array(
        'uid' => $commentID,
        'ad_id' => $id,
        'alarm_type' => Notification::$EDIT_AD_COMMENT,
        'create_time' => $now,
      ));

      $mail = new Mailer();
      $mail->send(OP_MAIL, '广告备注修改', $mail->create('comment-modified', array(
        'id' => $id,
      )));

      //重新取出comment表的备注集合
      $service = new AD();
      $comments = $service->get_ad_comments(array($id));

      $this->output(array(
        'code' => 0,
        'msg' => '添加成功。' . ($check ? '通知已发送。' : ''),
        'ad' => array(
          'cm_others' => $comments[$id],
          'comment' => '',
        ),
      ));
    }

    // 关联合同修改,需发送一枚通知
    if ($attr['agreement_id'] && $attr['agreement_id'] != $ad->agreement_id) {
      $log = new AgreementChangeLogModel([
        'ad_id' => $id,
        'date' => $attr['date'],
        'comment' => $attr['agreement_comment'],
        'origin' => $ad->agreement_id,
        'agreement_id' => $attr['agreement_id'],
        'is_correct' => $attr['is_correct'],
      ]);
      try {
        $log->save();
      } catch ( Exception $e) {
        $this->exit_with_error($e->getCode(), $e->getMessage(), 20);
      }
      $attr = Utils::array_omit($attr, 'date', 'agreement_comment');
      if ($attr['is_correct']) {
        unset($attr['is_correct']);
      } else {
        $agreements = new ADAgreementCollection(['ad_id' => $ad->id]);
        $agreements->fetch();
        $this->output([
          'code' => 0,
          'msg' => '新合同记录已创建。',
          'ad' => [
            'link_agreements' => $agreements->toJSON(),
          ]
        ]);
      }
    }

    try {
      $message = $attr['message'];
      unset($attr['message'], $attr['rmb'], $attr['status-time']); // 不需要的值
      $ad->update($attr, $message);

      if ($attr['status'] == ADModel::OFFLINE) {
        $this->updateES($id, [
          'status' => ADModel::OFFLINE,
          'status_label' => 'OFFLINE'
        ]);
      }
    } catch (ADException $e) {
      $this->exit_with_error($e->getCode(), $e->getMessage(), 400, SQLHelper::$info);
    }

    $result = array(
      'code' => 0,
      'msg'  => '修改完成',
      'ad'   => $ad->toJSON($attr),
    );

    $this->output( $result );
    return null;
  }

  /**
   * 删除广告
   *
   * @param $id
   */
  public function delete($id) {
    $ad = new ADModel(['id' => $id]);
    $ad->fetch();

    // 拒绝操作已经上线的广告
    if (in_array($ad->get('status'), [0, 1])) {
      $this->exit_with_error(50, '此广告已经推广，不能删除。您可以申请将其下线。', 400);
    }

    // 拒绝操作别人的广告
    $check = $ad->check_owner();
    if (!$check) {
      $this->exit_with_error(51, '您无权操作此广告', 403);
    }

    // 被编辑锁定的广告不能撤销
    $redis = $this->get_redis();
    $redis_key = self::REDIS_PREFIX . $id;
    $value = $redis->get($redis_key);
    if ($value) {
      $value = json_decode($value, true);
      $this->exit_with_error(52, '此广告正由' . $value['name'] . '审查中，请联系ta进行处理。', 403);
    }

    // 撤回替换广告申请
    $apply_service = new Apply();
    $apply_service->remove_replace_apply($id);

    // 撤回通知
    $notice = new Notification();
    $notice->set_status(array(
      'ad_id' => $id,
      'alarm_type' => array(Notification::$NEW_AD, Notification::$REPLACE_AD),
    ), Notification::$HANDLED);

    $attr = array(
      'status' => ADModel::DELETED,
    );
    $this->update($id, $attr);
  }

  public function deleteAgreement( $ad_id, $agreement_id ) {
    $ad = new ADModel(['id' => $ad_id]);
    $ad->fetch();

    if (!$ad->check_owner()) {
      $this->exit_with_error(61, '您无权操作此广告', 403);
    }
    
    $log = new AgreementChangeLogModel([
      'ad_id' => $ad_id,
      'agreement_id' => $agreement_id,
    ]);
    $check = $log->remove();

    if ($check) {
      $this->output([
        'code' => 0,
        'msg' => '解除关联成功',
      ]);
    } else {
      $this->exit_with_error(65, '解除关联失败');
    }
  }

  public function send_apply($id, $attr, ADModel $ad = null) {
    try {
      $apply = new ApplyModel($id, $attr, true);

      if ($ad) {
        $ad = $ad->toJSON();
        foreach ( $attr as $key => $value ) {
          if ($ad[$key]) {
            $attr[$key] = $ad[$key];
          }
        }
      }
      $this->output(array(
        'code' => 0,
        'msg' => '申请已提交',
        'ad' => array_merge($attr, $apply->toJSON())
      ));
    } catch (ADException $e) {
      $this->exit_with_error($e->getCode(), $e->getMessage(), $e->http_code, $e->debug);
    }
  }

  /**
   * 再次发送报备邮件
   * @param $id
   */
  public function resend_baobei_email($id) {
    $service = new AD();
    $passed = $service->check_baobei_pass($id);
    if ($passed) {
      $this->output(array(
        'code' => 0,
        'msg' => '改广告已经完成报备，无需再发邮件',
      ));
    }

    $info = $service->get_ad_info(array('id' => $id), 0, 1);
    $check = $this->baobei($_REQUEST['email'], $info);
    if ($check) {
      $this->output(array(
        'code' => 0,
        'msg' => '已重新发送报备邮件'
      ));
    } else {
      $this->exit_with_error(1, '发送报备邮件失败，请稍后重试。', 400);
    }
  }

  public function search() {
    $keyword = implode(' ', [trim($_REQUEST['keyword']), $_SESSION['fullname']]);
    $service = new AD();
    if ($keyword) {
      $result = $service->search_ad_from_es($keyword);
      $this->output([
        'code' => 0,
        'msg' => 'find these',
        'ads' => $result,
      ]);
    }
    $this->output([
      'code' => 0,
      'msg' => 'no keyword',
    ]);
  }

  /**
   * 返回完整路径
   * @param string $url
   *
   * @return string
   */
  private function createCompletePath( $url ) {
    return ( preg_match( '/^upload\//', $url ) ? UPLOAD_URL : '' ) . $url;
  }

  /**
   * 发送报备邮件
   *
   * @param $to
   * @param $attr
   *
   * @return bool
   */
  private function baobei( $to, $attr ) {
    $mail = new Baobei_Mailer();
    $subject = '无限点乐广告报备邮件';
    return $mail->send($to, $subject, $attr);
  }

  /**
   * 二次申请上线
   * @param $id
   * @param $attr
   * @param ADModel $ad
   *
   * @return array
   */
  private function reapply_ad( $id, $attr, ADModel $ad ) {
    $now = date( 'Y-m-d H:i:s' );
    $ad->update( array(
      'status'      => 2,
      'create_time' => $now,
    ) );
    $notice = new Notification();
    $notice->send( array(
      'ad_id'       => $id,
      'alarm_type'  => Notification::$NEW_AD,
      'create_time' => $now,
    ) );

    $mail    = new Mailer();
    $subject = "商务[{$_SESSION['fullname']}]再次提交广告：{$attr['channel']} {$attr['ad_name']}";
    $mail->send( OP_MAIL, $subject, $mail->create( 'ad-new', $ad->toJSON() ) );

    $this->output( [
      'code' => 0,
      'msg'  => '已发送申请',
      'ad' => [
        'status' => 2,
      ],
    ] );

    return array( $notice, $mail );
  }

  private function updateES( $id, $attr ) {
    $client = ClientBuilder::create()->setHosts(ES_HOSTS)->build();
    $params = array(
      'index' => 'ad_info',
      'type' => 'ad_info',
      'id' => $id,
    );
    $params['body'] = [
      'doc' => $attr,
    ];

    $client->update($params);
  }
} 