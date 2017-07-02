<?php
/**
 * Created by PhpStorm.
 * User: hemengyuan
 * Date: 16/2/17
 * Time: 下午2:33
 */

namespace diy\model;


use diy\service\ActivityCut;
use admin\service\AD;
use admin\service\Admin;
use admin\service\User;
use diy\utils\Utils;
use Exception;
use PDO;
use SQLHelper;

class ActivityCutModel extends Base
{
  const STATUS_NORMAL = 1;
  const STATUS_DEL = 0;
  const TYPE_CUSTOMER = 2;

  const FIELDS = array('rmb', 'user_id', 'start', 'end', 'comment', 'status', 'ad_id', 'title', 'type', 'cut_type');
  const TYPES = array('红包成本', '非红包成本', '客户成本', '其它成本');
  const CUT_TYPES = array(1 => '公司承担', 2 => '商务承担金额', 3 => '商务承担CPA');

  public function __construct(array $attr = null, array $options = null) {
    if ($attr) {
      parent::__construct($attr, $options);
    }
  }

  public function create($attr, $is_activity) {
    $attr['status'] = self::STATUS_NORMAL;
    $attr = Utils::array_pick($attr, self::FIELDS);
    $attr['admin'] = $_SESSION['admin_id'];
    $attr['create_time'] = date("Y-m-d H:i:s");
    $attr['ad_id'] = is_array($attr['ad_id']) ? implode(',', $attr['ad_id']) : $attr['ad_id'];
    $DB_write = $this->get_write_pdo();
    if (!SQLHelper::insert($DB_write, $is_activity ? 't_ad_activity' : 't_ad_cut', $attr)) {
      throw new Exception('添加失败', 100);
    }

    $attr['id'] = SQLHelper::$lastInsertId;

    $service = new ActivityCut();
    $service->add_activity_cut_detail($attr, $is_activity);

    $attr['ad_id'] = explode(',', $attr['ad_id']);
    $ad_service = new AD();
    $filters = array(
      'a.id' => array(
        'operator' => 'in',
        'data' => $attr['ad_id'],
      ),
    );
    $attr['ads'] = $ad_service->get_all_ad_info($filters, 0, PDO::FETCH_ASSOC);

    $admin_service = new Admin();
    $attr['admin'] = $admin_service->get_admin_name_by_id($attr['admin']);

    if ($is_activity) {
      $user_service = new User();
      $attr['user'] = $user_service->get_user($attr['user_id']);
    }

    return $attr;
  }

  public function update(array $attr = null) {
    $is_activity = $attr['is_activity'];
    unset($attr['is_activity']);
    $table = $is_activity ? 't_ad_activity' : 't_ad_cut';
    $id = $this->get('id');

    $attr = Utils::array_pick($attr, self::FIELDS);

    if ($attr['ad_id'] && is_array($attr['ad_id'])) {
      $attr['ad_id'] = implode(',', $attr['ad_id']);
    }
    $DB_write = $this->get_write_pdo();
    if (!SQLHelper::update($DB_write, $table, $attr, array('id' => $id))) {
      throw new Exception('修改失败', 101);
    }

    if ($attr['ad_id']) {
      $attr['ad_id'] = explode(',', $attr['ad_id']);
      $ad_service = new AD();
      $filters = array(
        'a.id' => array(
          'operator' => 'in',
          'data' => $attr['ad_id'],
        ),
      );
      $attr['ads'] = $ad_service->get_all_ad_info($filters, 0, PDO::FETCH_ASSOC);
    }

    if ($attr['user_id']) {
      $user_service = new User();
      $attr['user'] = $user_service->get_user($attr['user_id']);
    }

    $service = new ActivityCut();
    if (array_intersect(array_keys($attr), array('status', 'ad_id', 'rmb', 'start', 'end'))) {
      $table_detail = $is_activity ? 't_ad_activity_detail' : 't_ad_cut_detail';
      if (!$DB_write->exec("delete from $table_detail where source_id='$id'")) {
        throw new Exception('修改分广告数据失败', 102);
      }
    }
    if (array_intersect(array_keys($attr), array('ad_id', 'rmb', 'start', 'end'))) {
      $activity_cut = $service->get_activity_cut($id, $is_activity);
      $service->add_activity_cut_detail($activity_cut, $is_activity);
    }

    return $attr;
  }
}