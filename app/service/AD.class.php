<?php
/**
 * Created by PhpStorm.
 * User: 路佳
 * Date: 2015/2/6
 * Time: 16:23
 */

namespace diy\service;

use diy\utils\Utils;
use PDO;
use SQLHelper;
use diy\model\ADModel;

class AD extends Base {
  protected $order = array('create_time' => 'desc');

  /**
   * 导出广告深度任务的idfa数据
   *
   * @param $id
   * @param $start_date
   * @param $end_date
   * @param $delta
   *
   * @return array
   */
  public function get_ad_task_log($id, $start_date, $end_date, $delta) {
    $DB = $this->get_read_pdo();
    $sql = "SELECT `transfer_time`,`device_id`
            FROM `t_task_transfer_log`
            WHERE `task_id` IN(
              SELECT `id` FROM `t_task`
              WHERE `ad_id` = :id AND `delta` = :delta)
            AND (`transfer_time`>=:start_date AND `transfer_time` < :end_date)";
    $state = $DB->prepare($sql);
    $state->execute(array(
      ':id' => $id,
      ':start_date' => $start_date,
      ':end_date' => $end_date,
      ':delta' => $delta
    ));
    return $state->fetchAll(PDO::FETCH_ASSOC);
  }

  /**
   * 查询普通广告的手机串号数据（除对接接口的ios广告）
   * @param $id
   * @param $start_date
   * @param $end_date
   * @return array
   */
  public function get_ad_transfer_log($id, $start_date, $end_date) {
    $DB = $this->get_read_pdo();
    $sql = "SELECT `transfer_time`,`device_id`
            FROM `t_offer_transfer_log`
            WHERE `ad_id` = :id AND (`transfer_time` >= :start_date AND `transfer_time` < :end_date)";
    $state = $DB->prepare($sql);
    $state->execute(array(
      ':id' => $id,
      ':start_date' => $start_date,
      ':end_date' => $end_date
    ));
    return $state->fetchAll(PDO::FETCH_ASSOC);
  }

  /**
   * 查询ios广告手机串号数据
   * @param $id
   * @param $start_date
   * @param $end_date
   * @param $type
   * @return array
   */
  public function get_ios_transfer_log($id, $start_date, $end_date, $type) {
    $p_start_date = date('Ymd', strtotime($start_date));
    $p_end_date = date('Ymd', strtotime($end_date));

    $sql = "";
    if ($type == ADModel::IDFA_TRANSFER) { // 激活idfa
      $sql = "SELECT transfer_time,device_id,search_key
              FROM fdm_adp_ios_extra_transfer_log_di
              WHERE p_date >= '$start_date' AND p_date <= '$end_date' AND ad_id = '$id'
              ORDER BY transfer_time ASC
              limit 1000000";
    }

    if ($type == ADModel::IDFA_QUOTE) { // 回调idfa
      $sql = "SELECT max(adnotify_time) AS transfer_time,device_id
              FROM tb_income_transfer_ios_log
              WHERE p_date >= '$p_start_date' AND p_date <= '$p_end_date'
              AND adnotify_time >= '$start_date 00:00:00' AND adnotify_time <= '$end_date 23:59:59'
              AND ad_id = '$id'
              GROUP BY device_id
              ORDER BY transfer_time ASC
              limit 1000000";
    }

    $keplerService = new Kepler();
    $result = $keplerService->getData($sql);

    // log
    $logService = new ADOperationLogger();
    $logService->log($id, 'ad', 'export', $start_date . "," . $end_date . "," . count($result) . "," . $type);

    return $result;
  }

  /**
   * 取广告信息
   *
   * @param array $filters
   * @param int $page_start
   * @param int $pagesize
   * @param string $order
   * @param string|array $extra_table
   *
   * @return array
   */
  public function get_ad_info($filters, $page_start = 0, $pagesize = 10, $order = null, $extra_table = null) {
    $DB = $this->get_read_pdo();
    $filters =$this->move_field_to( $filters, 'status', 'a', [
      ADModel::ONLINE,
      ADModel::OFFLINE,
      ADModel::APPLY,
      ADModel::REPLACE,
      ADModel::REJECTED,
    ] );
    $filters = $this->move_field_to( $filters, 'ad_name', 'a' );
    $filters = $this->move_field_to( $filters, 'owner', 'b' );
    $filters = $this->move_field_to($filters, 'ad_app_type', 'a');
    list($conditions, $params) = $this->parse_filter( $filters );
    if ($order) {
      $order = 'ORDER BY ' . $this->get_order( $order );
    }
    $limit = $pagesize ? "LIMIT $page_start, $pagesize" : '';
    $tables = [ADModel::$T_RMB, ADModel::$T_IOS_INFO];
    if ($extra_table) {
      $extra_table = is_array($extra_table) ? $extra_table : [$extra_table];
    } else {
      $extra_table = $tables;
    }
    $tables = $this->parse_extra_tables( $extra_table, 'h' );
    $fields = $extra_table ? $this->getExtraTablesFields($extra_table) : '';
    if (Auth::is_cp()) {
      $tables .= "\nLEFT JOIN `t_adinfo_diy` diy ON i.`diy_id`=diy.`id`";
      $fields .= ',`start_time`,`end_time`';
    }

    $sql = "SELECT a.`id`,a.`ad_name`,`ad_size`,a.`create_time`,a.`ad_app_type`,`others`,`ad_lib`,
              `ad_sdk_type`,a.`status`,`pack_name`,a.`quote_rmb`,b.`agreement_id`,`cid`,a.`cate`,
              a.`status_time`,`execute_owner`,`ad_type`,`ad_desc`,`pic_path`,`ad_shoot`,a.`ad_url`,
              `url`,`user`,`pwd`,`feedback`,b.`cycle`,`net_type`,`ratio`,b.`owner`,
              `company`,`company_short`,c.`agreement_id` AS `aid`,`province_type`,
              COALESCE(NULLIF(`company_short`,''),d.`alias`,`channel`) AS `channel` $fields 
            FROM `t_adinfo` a
              JOIN `t_ad_source` b ON a.`id`=b.`id`
              LEFT JOIN `t_agreement` c ON b.`agreement_id`=c.`id`
              LEFT JOIN `t_channel_map` d ON b.`channel`=d.`id`
              LEFT JOIN `t_channel_map` e ON c.`channel_id`=e.`id`
              $tables
            WHERE $conditions
            $order
            $limit";
    $state = $DB->prepare($sql);
    $state->execute($params);

    $result = $state->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_UNIQUE);
    if ($pagesize == 1) {
      $key = array_keys( $result )[0];
      $result = array_pop( $result );
      $result['id'] = $key;
    }
    return $result;
  }

  public function get_ad_app_type( array $filters ) {
    $DB = $this->get_read_pdo();
    list($conditions, $params) = $this->parse_filter($filters);
    $sql = "SELECT `id`,`ad_app_type`
            FROM `t_adinfo` a
            WHERE {$conditions}";
    $state = $DB->prepare($sql);
    $state->execute($params);
    return $state->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE);
  }

  /**
   * 取商务的广告数量
   * @author Meathill
   *
   * @param array $filters   *
   * @param array|string $extraTables
   *
   * @return int $int
   */
  public function get_ad_number($filters, $extraTables = null) {
    $DB = $this->get_read_pdo();
    $filters = $this->move_field_to($filters, 'status', 'a');
    $filters = $this->move_field_to($filters, 'ad_name', 'a');
    list($conditions, $params) = $this->parse_filter( $filters );
    if ($extraTables) {
      $extraTables = is_array($extraTables) ? $extraTables : [$extraTables];
    }
    $tables = $this->parse_extra_tables($extraTables);
    $sql = "SELECT COUNT('X')
            FROM `t_adinfo` a 
              JOIN `t_ad_source` b ON a.`id`=b.`id`
              LEFT JOIN `t_agreement` c ON b.`agreement_id`=c.`id`
              $tables
            WHERE $conditions";
    $state = $DB->prepare($sql);
    $state->execute($params);
    return (int)$state->fetch(PDO::FETCH_COLUMN);
  }

  public function get_ad_ids(array $filters) {
    $DB = $this->get_read_pdo();

    list($conditions, $params) = $this->parse_filter($filters);
    $sql = "SELECT a.`id`
            FROM `t_adinfo` a 
              JOIN `t_ad_source` b ON a.`id`=b.`id`
            WHERE $conditions";
    $state = $DB->prepare($sql);
    $state->execute($params);
    return $state->fetchAll(PDO::FETCH_COLUMN);
  }

  public function get_ad_info_by_pack_name($pack_name, $platform = 1) {
    $info = $this->get_ad_info(array(
      'pack_name' => $pack_name,
      'ad_app_type' => $platform,
      'ad_sdk_type' => 1,
      'status' => array(0, 1),
    ), 0, 1, array(
      'status' => 'ASC',
      'create_time' => 'DESC',
    ), false);
    $omits = ['others', 'ad_url', 'pack_md5', 'cid', 'agreement_id', 'url', 'user', 'password', 'feedback', 'cycle', 'quote_rmb', 'owner', 'execute_owner'];
    return $info ? Utils::array_omit($info, $omits) : array();
  }

  public function get_all_labels($method = null) {
    $DB = $this->get_read_pdo();
    $method = $method ? $method : PDO::FETCH_ASSOC;
    $sql = "SELECT `id`, `label`
            FROM `t_ad_labels`";
    return $DB->query($sql)->fetchAll($method);
  }

  public function get_all_permissions()  {
    $DB = $this->get_read_pdo();
    $sql = "SELECT `id`, `permission_interface` AS `name`, `permission_name`
            FROM `t_ad_permission`";
    $all = $DB->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    foreach ( $all as &$value ) {
      $value['name'] = substr($value['name'], strrpos($value['name'], '.') + 1);
    }
    return array_values($all);
  }

  public function get_permissions( $filters ) {
    $DB = $this->get_read_pdo();
    list($conditions, $params) = $this->parse_filter( $filters, ['is_append' => true] );
    $sql = "SELECT m.`id`, `permission_info`
            FROM `t_ad_permission_match` m LEFT JOIN `t_ad_permission` a ON m.`id`=a.`id`
            WHERE `permission_info` IS NOT NULL $conditions";
    $state = $DB->prepare($sql);
    $state->execute($params);
    return $state->fetchAll(PDO::FETCH_KEY_PAIR);
  }

  public function get_rmb_out_by_ad($ad_ids) {
    $DB = $this->get_read_pdo();
    $ad_ids = is_array($ad_ids) ? $ad_ids : [$ad_ids];
    $placeholder = implode(',', array_fill(0, count($ad_ids), '?'));
    $sql = "SELECT `id`,`rmb_out`
            FROM `t_adinfo_rmb`
            WHERE `id` IN ($placeholder)";
    $state = $DB->prepare($sql);
    $state->execute($ad_ids);
    return $state->fetchAll(PDO::FETCH_KEY_PAIR);
  }

  public function get_ad_rmb( array $filters ) {
    $DB = $this->get_read_pdo();
    list($conditions, $params) = $this->parse_filter( $filters );
    $sql = "SELECT a.`id`, `ad_name`, `pack_name`, `step_rmb`, `seq_rmb`,
               `rmb`, `num`, `status`
            FROM `t_adinfo` AS a
              JOIN `t_adinfo_rmb` AS b ON a.`id`=b.`id`
            WHERE $conditions";
    $state = $DB->prepare($sql);
    $state->execute($params);
    return $state->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_UNIQUE);
  }

  public function get_baobei_emails( array $baobei ) {
    $DB = $this->get_read_pdo();
    $baobei = implode(",", array_fill(0, count($baobei), '?'));
    $sql = "SELECT `ad_id`, `to_email`, `reply_time`, `status`
            FROM `t_ad_baobei`
            WHERE `ad_id` IN ('$baobei')
            ORDER BY `id` DESC";
    $state = $DB->prepare($sql);
    $state->execute($baobei);
    $emails = $state->fetchAll(PDO::FETCH_ASSOC);

    // 整理后再输出
    $result = array();
    foreach ( $emails as $email ) {
      $item = $result[$email['ad_id']];
      if (!is_array($item)) {
        $item = array(
          'is_baobei' => false, // 是否报备通过
          'is_baobei_failed' => null, // 是否被拒
          'baobei' => '', // 最近的报备邮箱
        );
      }
      if ($item['is_baobei']) { // 有一封过就算过
        continue;
      }
      if ($email['status'] == 1) {
        $item['is_baobei'] = true;
      }
      if ($email['status'] == 2 && $item['is_baobei_failed'] === null) { // 只有
        $item['is_baobei_failed'] = true;
      }
      if ($email['status'] == 0) {
        $item['is_baobei_failed'] = false;
      }
      $item['baobei'] = $email['to_email'];
      $result[$email['ad_id']] = $item;
    }
    return $result;
  }

  public function get_comments( array $filters ) {
    $DB = $this->get_read_pdo();
    $filters['a.status'] = 0;
    list($conditions, $params) = $this->parse_filter( $filters);
    $sql = "SELECT `pack_name`,`comment`,`create_time`,`NAME` AS `author`
            FROM `t_ad_comment` a 
              LEFT JOIN `t_admin` b ON a.`author`=b.`id`
            WHERE $conditions
            ORDER BY `pack_name`, a.`id` DESC";
    $state = $DB->prepare($sql);
    $state->execute($params);
    return $state->fetchAll(PDO::FETCH_ASSOC);
  }

  public function get_ad_comments( array $ad_id ) {
    $keys = SQLHelper::get_in_fields($ad_id);
    $DB = $this->get_read_pdo();
    $sql = "SELECT `ad_id`,`comment`,`reply`,a.`status`,`NAME` AS `handler`,
              `solve_time`,`create_time`
            FROM `t_diy_ad_comment` a 
              LEFT JOIN `t_admin` b ON a.`handle`=b.`id`
            WHERE `ad_id` IN ($keys)";
    $state = $DB->prepare($sql);
    $state->execute($ad_id);
    $comments = $state->fetchAll(PDO::FETCH_ASSOC);

    $result = array();
    foreach ( $comments as $comment ) {
      $item = $result[$comment['ad_id']];
      $item = is_array($item) ? $item : array();
      $comment['status'] = (int)$comment['status'];
      $item[] = $comment;
      $result[$comment['ad_id']] = $item;
    }
    return $result;
  }

  public function get_ad_comments_by_id($ad_id) {
    $DB = $this->get_read_pdo();
    $sql = "SELECT `ad_id`,`comment`,`reply`,a.`status`,`NAME` AS `handler`,
              `solve_time`,`create_time`,`author`
            FROM `t_diy_ad_comment` a 
              LEFT JOIN `t_admin` b ON a.`handle`=b.`id`
            WHERE `ad_id`=:ad_id";
    $state = $DB->prepare($sql);
    $state->execute([':ad_id' => $ad_id]);
    return $state->fetchAll(PDO::FETCH_ASSOC);
  }

  public function check_ad_owner( $id ) {
    $DB = $this->get_read_pdo();
    $me = $_SESSION['id'];
    $im_cp = $_SESSION['role'] == Auth::$CP_PERMISSION;
    $param = [
      ':id' => $id,
      ':me' => $me,
    ];
    if ($im_cp) {
      $owner = ' AND (`create_user`=:me OR `channel_id`=:channel_id)';
      $param['channel_id'] = $_SESSION['channel_id'];
    } else {
      $owner = ' AND (s.`owner`=:me OR `execute_owner`=:me)';
    }
    $sql = "SELECT 'x'
            FROM `t_adinfo` i 
              JOIN `t_ad_source` s ON i.`id`=s.`id`
              LEFT JOIN `t_agreement` c ON s.`agreement_id`=c.`id`
            WHERE i.`id`=:id $owner";
    $state = $DB->prepare($sql);
    $state->execute( $param );
    return $state->fetchColumn();
  }

  public function check_baobei_pass( $id ) {
    $DB = $this->get_read_pdo();
    $sql = "SELECT 'x'
            FROM `t_ad_baobei`
            WHERE `ad_id`=:id AND `status`=1";
    $state = $DB->prepare($sql);
    $state->execute(array(':id' => $id));
    return $state->fetchColumn();
  }

  public function exist( $id ) {
    $DB = $this->get_read_pdo();
    $sql = "SELECT 'x'
            FROM `t_adinfo`
            WHERE `id`=:id";
    $state = $DB->prepare($sql);
    $state->execute([':id' => $id]);
    return $state->fetchColumn();
  }

  public function search_ad_from_es($keyword) {
    $keywords = explode(' ', $keyword);
    foreach ($keywords as $key => $word) {
      if (is_numeric($word)) {
        $keywords[$key] = '*' . $word . '*';
      } else {
        $keywords[$key] = '\"*' . $word . '*\"';
      }
    }

    $es_service = new ES();
    $result = $es_service->query_sql('ad_info', 'ad_info', array('_all' => $keywords), array('id', 'channel_name', 'ad_name', 'cid', 'owner', 'company'), 20);
    $ads = array();
    foreach ($result['hits']['hits'] as $value) {
      $ads[] = $value['_source'];
    }
    return $ads;
  }

  public function set_permissions( $id, $permission ) {
    $DB = $this->get_write_pdo();
    $sql = "DELETE FROM `t_ad_permission_match`
            WHERE `ad_id`=:id";
    $state = $DB->prepare($sql);
    $state->execute(array(':id' => $id));

    foreach ( $permission as $key => $value ) {
      $permission[$key] = array(
        'ad_id' => $id,
        'permission_id' => $value
      );
    }
    return SQLHelper::insert_multi($DB, 't_ad_permission_match', $permission);
  }

  public function get_all_ad_rmb_change($start, $end) {
    $DB  = $this->get_read_pdo();
    $sql = "select *
            from t_ad_rmb_change_log
            where datetime>=:start and datetime<=:end and type='step_rmb'";
    $state = $DB->prepare($sql);
    $state->execute([':start' => $start, ':end' => $end . ' 23:59:59']);
    $log = $state->fetchAll(PDO::FETCH_ASSOC);
    $rmb = array();
    foreach ($log as $l) {
      $pack_name = $l['pack_name'];
      if (!$rmb[$pack_name]) {
        $rmb[$pack_name] = array(
          'min' => 1000,
          'max' => 0,
        );
      }
      if ($rmb[$pack_name]['min'] > $l['origin']) {
        $rmb[$pack_name]['min'] = $l['origin'];
      }
      if ($rmb[$pack_name]['max'] < $l['origin']) {
        $rmb[$pack_name]['max'] = $l['origin'];
      }
      if ($rmb[$pack_name]['min'] > $l['new']) {
        $rmb[$pack_name]['min'] = $l['new'];
      }
      if ($rmb[$pack_name]['max'] < $l['new']) {
        $rmb[$pack_name]['max'] = $l['new'];
      }
    }
    return $rmb;
  }

  public function get_all_basic_ad_info($filters) {
    $DB = $this->get_read_pdo();
    $filters = $this->move_field_to( $filters, 'status', 'a', [
      ADModel::ONLINE, ADModel::OFFLINE
    ] );
    $filters = $this->move_field_to( $filters, 'ad_name', 'a' );
    list($conditions, $params) = $this->parse_filter( $filters );
    $sql = "SELECT a.id AS `ad_id`, a.ad_name, ad_size, create_time, ad_app_type, others,
              ad_sdk_type, step_rmb, label, a.status, pack_name,`quote_rmb`,
              `click_url` IS NOT NULL AS `has_api`,
              COALESCE(NULLIF(`company_short`,''),d.`alias`,`channel`) AS `channel`
            FROM t_adinfo AS a
              JOIN t_ad_source AS b ON a.id=b.id
              LEFT JOIN `t_adinfo_ios` i ON a.`id`=i.`ad_id`
              LEFT JOIN `t_agreement` c ON b.`agreement_id`=c.`id`
              LEFT JOIN `t_channel_map` d ON b.`channel`=d.`id`
              LEFT JOIN `t_channel_map` e ON c.`channel_id`=e.`id`
              LEFT JOIN t_ad_labels as l ON a.`ad_type`=l.id             
            WHERE $conditions";
    $state = $DB->prepare($sql);
    $state->execute($params);
    return $state->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_UNIQUE);
  }

  public function get_all_ad_info(array $filters = array()) {
    $DB = $this->get_read_pdo();

    if (!array_key_exists('status', $filters)) {
      $filters['a.status'] = array(
        'operator' => 'in',
        'data' => array(0, 1, 2, 3, 4),
      ); // 上线，下线，申请，替换，被据
    }
    list($conditions, $params) = $this->parse_admin_filter($filters);
    $sql = "SELECT a.id,a.ad_name,cid,label,a.status,seq_rmb,others,cpc_cpa,
              create_time,ad_app_type,ad_sdk_type,quote_rmb,step_rmb,pack_name,feedback,b.cycle,
              url,`user`,pwd,b.owner,execute_owner,
              o.location,o.`NAME` AS `owner_name`,eo.`NAME` AS `execute_owner_name`,
              COALESCE(NULLIF(`company`,''),d.`full_name`) AS `full_name`,
              COALESCE(NULLIF(company_short,''),d.`alias`,b.channel) AS channel,
              ifnull(c.company_type,d.type) AS channel_type,
              c.id as agreement_id,company
            FROM t_adinfo AS a
              JOIN t_ad_source AS b ON a.id=b.id
              LEFT JOIN t_agreement AS c ON b.agreement_id=c.id
              LEFT JOIN t_channel_map AS d ON b.channel=d.id
              LEFT JOIN t_channel_map e ON c.`channel_id`=e.`id`
              LEFT JOIN t_admin AS o ON b.owner=o.id
              LEFT JOIN t_admin AS eo ON b.execute_owner=eo.id
              LEFT JOIN t_ad_labels AS g ON a.ad_type=g.id
            WHERE $conditions";

    $state = $DB->prepare($sql);
    $state->execute($params);
    return $state->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_UNIQUE);
  }

  public function get_ad_info_by_id($id, array $filters = null) {
    $filters = $filters ? $filters : array();
    if (array_key_exists('status', $filters)) {
      $filters['a.status'] = ADModel::OFFLINE; // 下线广告
      unset($filters['status']);
    }
    list($conditions, $params) = $this->parse_filter($filters, [ 'is_append' => true ] );
    $sql = "SELECT a.`ad_name`,`cid`,`cpc_cpa`,`ad_app_type`,a.`status`,
              COALESCE(NULLIF(`company_short`,''),d.`alias`,e.`alias`,`channel`) AS `channel`,
              b.`channel` AS `channel_id`,`url`,`user`,`pwd`,`create_time`,`quote_rmb`,
              b.`agreement_id`,`status_time`,`location`,b.`owner`,b.`execute_owner`,f.`NAME` as `owner_name`
            FROM t_adinfo AS a
              JOIN t_ad_source AS b ON a.id=b.id
              JOIN t_admin as f on b.owner=f.id
              LEFT JOIN `t_agreement` c ON b.`agreement_id`=c.`id`
              LEFT JOIN `t_channel_map` d ON b.`channel`=d.`id`
              LEFT JOIN `t_channel_map` e ON c.`channel_id`=e.`id`
            WHERE a.id=:id $conditions";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $params = array_merge($params, array(':id' => $id));
    $state->execute($params);
    return $state->fetch(PDO::FETCH_ASSOC);
  }

  public function get_ad_channel_by_id($id) {
    $DB = $this->get_read_pdo();

    $sql = "SELECT ifnull(c.company_short,ifnull(b.alias,a.channel)) as channel
            FROM t_ad_source AS a LEFT JOIN t_channel_map AS b ON a.channel = b.id
            LEFT JOIN t_agreement AS c ON a.agreement_id = c.id
            WHERE a.id=:id";
    $state = $DB->prepare($sql);
    $state->execute(array(':id' => $id));
    return $state->fetchColumn();
  }

  public function get_quote_by_ads($start, $end, $ad_ids, $left_adids = null) {
    $range = [':start' => $start, ':end' => $end];

    list($conditions, $params) = $this->parse_filter(['ad_id' => $ad_ids], ['is_append' => true]);
    $params = array_merge($range, $params);
    $sql    = "SELECT `ad_id`, a.`id`, `ad_id`, a.`quote_date`, a.`quote_rmb`, nums AS cpa,
                  `ad_name`,`cid`,`ad_app_type`
               FROM `t_adquote` a
                 LEFT JOIN `t_adinfo` b ON a.`ad_id` = b.`id`
                 LEFT JOIN `t_ad_source` c ON a.`ad_id` = c.`id`
               WHERE `quote_date`>=:start AND `quote_date`<=:end $conditions";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $state->execute($params);
    $quotes = $state->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_GROUP);

    $result = [];
    foreach ($quotes as $ad_id => $value) {
      for ($i = 0, $len = count($value); $i < $len;) {
        $quote = $value[$i];
        $ad = [
          'id' => $quote['id'],
          'ad_id' => $quote['ad_id'],
          'ad_name' => $quote['ad_name'],
          'ad_app_type' => $quote['ad_app_type'],
          'cid' => $quote['cid'],
          'quote_start_date' => $quote['quote_date'],
          'quote_end_date' => $quote['quote_date'],
          'quote_rmb' => round($quote['quote_rmb'] / 100, 2),
          'income' => 0,
          'cpa' => 0,
          'start' => $start,
          'end' => $end
        ];
        $ad['income'] += $quote['quote_rmb'] * $quote['cpa'];
        $ad['cpa']    += $quote['cpa'];

        // 只有一条记录
        if ($len == 1) {
          $ad = array_merge($ad, ['income' => round($ad['income'] / 100, 2), 'cpa_after' => $ad['cpa'], 'quote_rmb_after' => $ad['quote_rmb']]);
          $result[] = $ad;
          break;
        }

        // 对比前后两条记录的单价，计算推广期间和CPA数量
        for ($j = $i + 1; $j < $len; $j++) {
          if ($quote['quote_rmb'] != $value[$j]['quote_rmb']) { // 单价不一样，跳出循环
            $ad['quote_end_date'] = $value[$j-1]['quote_date'];
            $ad = array_merge($ad, ['income' => round($ad['income'] / 100, 2), 'cpa_after' => $ad['cpa'], 'quote_rmb_after' => $ad['quote_rmb']]);
            $result[] = $ad;
            break;
          } else {
            $ad['income'] += $value[$j]['quote_rmb'] * $value[$j]['cpa'];
            $ad['cpa']    += $value[$j]['cpa'];
            $ad['quote_end_date'] = $value[$j]['quote_date'];
          }
        }

        // 指针后移
        $i = $j;

        if ($j == $len) {
          $ad = array_merge($ad, ['income' => round($ad['income'] / 100, 2), 'cpa_after' => $ad['cpa'], 'quote_rmb_after' => $ad['quote_rmb']]);
          $result[] = $ad;
        }
      }
    }

    // 加上有cpa，没有渠道cpa的广告
    if ($left_adids) {
      // 只保留有选中的广告
      $left_adids            = array_intersect($left_adids, $ad_ids);
      $transfer_stat_service = new ADTransferStat();
      $no_quote_ads          = $transfer_stat_service->get_ad_transfer_stat_of_quote($start, $end, ['ad_id' => $left_adids]);

      foreach ($left_adids as $ad_id) {
        $no_quote_ad = [
          'quote_start_date' => $start,
          'quote_end_date' => $end,
          'quote_rmb' => 0,
          'income' => 0,
          'cpa' => 0,
          'cpa_after' => 0,
          'quote_rmb_after' => 0,
          'start' => $start,
          'end' => $end,
          'ad_name' => $no_quote_ads[$ad_id]['ad_name'],
          'cid' => $no_quote_ads[$ad_id]['cid'],
          'id' => $no_quote_ads[$ad_id]['id'],
          'ad_app_type' => $no_quote_ads[$ad_id]['ad_app_type'],
          'ad_id' => $ad_id
        ];
        $result[] = $no_quote_ad;
      }
    }

    return $result;
  }

  public function get_ad_id_by_app_type($ad_app_type) {
    $DB = $this->get_read_pdo();
    $sql = "SELECT `id`
            FROM `t_adinfo`
            WHERE `ad_app_type`=:ad_app_type";
    $state = $DB->prepare($sql);
    $state->execute([':ad_app_type' => $ad_app_type]);
    return $state->fetchAll(PDO::FETCH_COLUMN);
  }

  public function select_ad_join_source_create_time($id) {
    $DB = $this->get_read_pdo();

    $sql = "SELECT a.`id`,`ad_name`,`create_time`,a.`status`,`ad_app_type`,
              ifnull(c.`alias`,b.`channel`) as channel,`cid`,`owner`,`execute_owner`,
              `pack_name`,`seq_rmb`
            FROM `t_adinfo` a
              JOIN `t_ad_source` b ON a.`id`=b.`id`
              LEFT JOIN `t_channel_map` as c ON b.`channel`=c.`id`
            WHERE a.`status`>=0 AND a.`id`=:id";
    $state = $DB->prepare($sql);
    $state->execute([':id' => $id]);
    return $state->fetch(PDO::FETCH_ASSOC);
  }

  public function get_online_packname_count() {
    $DB = $this->get_read_pdo();

    $time = date("Y-m-d H:i:s", time() - 3600 * 2);
    $sql = "SELECT platform,count(distinct(ad_name))
            FROM t_ad_bucket AS a
              JOIN (t_competitors_ads AS d,t_adinfo AS b,t_adinfo_rmb AS c)
              ON a.pack_name=b.pack_name AND b.id=c.id AND a.pack_name=d.pack_name
            WHERE  b.status=0 AND c.rmb>0 AND b.oversea=0 AND a.create_date=CURDATE()
              AND d.company='点乐' and d.`current_time`>='$time'
            GROUP BY d.platform";
    return $DB->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR);
  }

  /**
   * 取广告负责人的变更记录,从中计算这些广告在这个周期的实际负责人,避免日后统计错误
   * 这个函数里的 start 和 end 理论上必须是同一个月
   *
   * @param string $start 开始日期
   * @param string $end 结束日期
   * @param array $ad_info 广告信息
   *
   * @return array
   */
  public function get_ad_owner_operation_log( $start, $end, $ad_info ) {
    list($conditions, $params) = $this->parse_filter([
      'type' => 1,
      'ad_id' => array_keys($ad_info)
    ]);
    $sql = "SELECT `ad_id`,`new`,`origin`,`type`,`date`
            FROM `t_ad_owner_operation_log`
            WHERE $conditions
            ORDER BY `date`";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $state->execute($params);
    $logs = $state->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_GROUP);

    $result = [];
    $owner = [];
    // 每条广告的记录都保留之前的最后一个,之后的第一个,和周期中所有的
    foreach ( $logs as $ad_id => $log ) {
      $record = [];
      foreach ( $log as $item ) {
        if ($item['date'] <= $start) { // 每月一号也算之前
          $record['before'] = $item;
        } else if ($item['date'] > $end) {
          $record['after'] = $item;
          break;
        } else {
          $record['in'][$item['date']] = $item; // 同样的一天只保留最后一条记录
        }
      }
      if (count($record) == 1 && $record['before']) { // 之前有记录且没再改过
        if ($record['before']['new'] != $ad_info[$ad_id]['owner']) {
          error_log("[广告负责人错误]($start~$end) $ad_id 应为:{$record['before']['new']}; 实为: {$ad_info[$ad_id]['owner']}\n", 3, '/tmp/v5_ad_owner_error.log');
        }
        continue;
      }
      if (!array_key_exists('in', $record) && $record['after']) { // 中间没有记录,之后有记录
        $owner[$ad_id] = $record['after']['origin']; // 之后调整过,当时的 owner 是其他人
        continue;
      }
      $record = $record['in'];
      $offset = $start;
      foreach ( $record as $item ) {
        $end_date = date('Y-m-d', strtotime($item['date']) - 86400);
        $result[$offset . '_' . $end_date][$ad_id] = $item['origin'];
        $offset = $item['date'];
      }
      if ($record) {
        $last = array_pop($record);
        $result[$last['date'] . '_' . $end][$ad_id] = $last['new'];
      }
    }
    return [$result, $owner];
  }

  public function getADByChannel( $id, $page = 0, $pageSize = 20 ) {
    $limit = '';
    if ($pageSize) {
      $start = $pageSize * $page;
      $limit = "LIMIT $start,$pageSize";
    }
    $sql = "SELECT `pack_name`,a.`ad_name`,`ad_app_type`,`feedback`
            FROM `t_adinfo` a
              JOIN `t_ad_source` b ON a.`id`=b.`id`
              JOIN `t_agreement` c ON b.`agreement_id`=c.`id`
            WHERE `channel_id`=:id AND a.`status` IN (:online,:offline)
            GROUP BY `pack_name`
            $limit";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $state->execute([
      ':id' => $id,
      ':online' => ADModel::ONLINE,
      ':offline' => ADModel::OFFLINE,
    ]);
    return $state->fetchAll(PDO::FETCH_ASSOC);
  }

  public function countADByChannel( $id ) {
    $sql = "SELECT COUNT(DISTINCT(`pack_name`))
            FROM `t_adinfo` a
              JOIN `t_ad_source` b ON a.`id`=b.`id`
              JOIN `t_agreement` c ON b.`agreement_id`=c.`id`
            WHERE c.`channel_id`=:id AND a.`status` IN (:online,:offline)";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $state->execute([
      ':id' => $id,
      ':online' => ADModel::ONLINE,
      ':offline' => ADModel::OFFLINE,
    ]);
    return $state->fetchColumn();
  }

  protected function parse_filter(array $filters = null, array $options = array() ) {
    $defaults = ['to_string' => true];
    $options = array_merge($defaults, $options);
    $filters = $this->move_field_to( $filters, 'id', 'a' );
    if (isset($filters['start'])) {
      $filters['create_time'][] = array(
        'operator' => '>=',
        'data' => $filters['start'],
      );
      unset($filters['start']);
    }
    if (isset($filters['end'])) {
      $filters['create_time'][] = array(
        'operator' => '<=',
        'data' => $filters['end'],
      );
      unset($filters['end']);
    }
    if (isset($filters['ios_start_time'])) {
      $filters['adnotify_time'][] = array(
        'operator' => '>=',
        'data' => $filters['ios_start_time'],
      );
      unset($filters['ios_start_time']);
    }
    if (isset($filters['ios_end_time'])) {
      $filters['adnotify_time'][] = array(
        'operator' => '<',
        'data' => $filters['ios_end_time'],
      );
      unset($filters['ios_end_time']);
    }
    if (isset($filters['ad_start_time'])) {
      $filters['transfer_time'][] = array(
        'operator' => '>=',
        'data' => $filters['ad_start_time'],
      );
      unset($filters['ad_start_time']);
    }
    if (isset($filters['ad_end_time'])) {
      $filters['transfer_time'][] = array(
        'operator' => '<',
        'data' => $filters['ad_end_time'],
      );
      unset($filters['ad_end_time']);
    }
    if (isset($filters['off_start_time'])) {
      $filters['status_time'][] = array(
        'operator' => '>=',
        'data' => $filters['off_start_time']
      );
      unset($filters['off_start_time']);
    }
    if (isset($filters['off_end_time'])) {
      $filters['status_time'][] = array(
        'operator' => '<',
        'data' => $filters['off_end_time']
      );
      unset($filters['off_end_time']);
    }
    $spec = array('keyword', 'salesman');
    $pick = Utils::array_pick($filters, $spec);
    $filters = Utils::array_omit($filters, $spec);
    list($conditions, $params) = parent::parse_filter( $filters, ['to_string' => false]);
    foreach ($pick as $key => $value) {
      switch ($key) {
        case 'keyword':
          if ($value) {
            $conditions[] = "(a.`ad_name` LIKE :keyword OR `channel` LIKE :keyword OR `cid` LIKE :keyword)";
            $params[':keyword'] = '%' . $value . '%';
          }
          break;

        case 'salesman':
          if ($value) {
            $conditions[] = "(b.`owner`=:salesman OR `execute_owner`=:salesman)";
            $params[':salesman'] = $value;
          }
          break;
      }
    }
    if ($options['to_string']) {
      $conditions = count($conditions) ? implode(' AND ', $conditions) : 1;
    }
    if ($options['is_append'] && !is_array($conditions) && $conditions) {
      $conditions = ' AND ' . $conditions;
    }
    return array($conditions, $params);
  }

  protected function parse_admin_filter( array $filters = null, array $options = array() ) {
    $defaults = ['to_string' => true];
    $options = array_merge($defaults, $options);
    $spec = array('keyword', 'salesman', 'channel', 'follow');
    if (isset($filters['ad_name'])) {
      $filters['a.ad_name'] = $filters['ad_name'];
      unset($filters['ad_name']);
    }
    if (isset($filters['start'])) {
      $filters['create_time'][] = array(
        'operator' => '>=',
        'data' => $filters['start'],
      );
      unset($filters['start']);
    }
    if (isset($filters['end'])) {
      $filters['create_time'][] = array(
        'operator' => '<=',
        'data' => $filters['start'],
      );
      unset($filters['end']);
    }
    if (isset($filters['owner'])) {
      $filters['b.owner'] = $filters['owner'];
      unset($filters['owner']);
    }
    $pick = Utils::array_pick($filters, $spec);
    $filters = Utils::array_omit($filters, $spec);
    list($conditions, $params) = parent::parse_filter($filters, array('to_string' => false));
    foreach ($pick as $key => $value) {
      switch ($key) {
        case 'keyword':
          $conditions[] = " (a.`ad_name` LIKE :keyword OR ifnull(company_short,ifnull(d.alias,b.channel)) LIKE :keyword)";
          $params[':keyword'] = '%' . $value . '%';
          break;

        case 'salesman':
          $conditions[] = " (b.`owner`=:salesman OR `execute_owner`=:salesman OR d.`vip_sales`=:salesman)";
          $params[':salesman'] = $value;
          break;

        case 'channel':
          $conditions[] = " ifnull(company_short,ifnull(d.alias,b.channel))=:channel";
          $params[':channel'] = $value;
          break;

        case 'follow':
          $conditions[] = ' b.owner!=execute_owner';
          break;
      }
    }
    $conditions = $options['to_string'] ? ($options['is_append'] ? ' and ' : '') . implode(' AND ', $conditions) : $conditions;
    return array($conditions, $params);
  }

  /**
   * @param $extra_table
   * @param string|int $count
   *
   * @return array
   */
  protected function parse_extra_tables( array $extra_table = null, $count = 98 ) {
    if (!$extra_table) {
      return '';
    }
    $tables = '';
    $count = is_string( $count ) ? ord( $count ) : $count;
    foreach ( $extra_table as $table ) {
      $alias = chr($count++);
      $id = $table == 't_adinfo_ios' ? '`ad_id`' : '`id`';
      $tables .= " LEFT JOIN $table $alias ON a.`id`=$alias.$id\n";
    }
    return $tables;
  }

  private function getExtraTablesFields( array $extra_table = null ) {
    $fields = [];
    if (in_array( ADModel::$T_RMB, $extra_table)) {
      $fields[] = 'num';
    }
    return $fields ? ',`' . implode( '`,`', $fields ) . '`' : '';
  }
}