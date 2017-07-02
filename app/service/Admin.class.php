<?php
/**
 * Created by PhpStorm.
 * User: 路佳
 * Date: 2015/2/6
 * Time: 18:24
 */

namespace diy\service;


use PDO;
use diy\utils\Utils;

class Admin extends Base {
  const BANNED = 0;
  const NORMAL = 1;

  const SALES_DIRECTOR = 100;
  const SALES_AREA_DIRECTOR = 50;
  const SALES = 1;
  const SALES_ASSISTANT = 2;
  const SALES_FOLLOW = 3;
  const SALE_BOSS = 28;
  const SALE = 6;
  const SALE_MANAGER = 5;
  const ADMIN = 0;

  const SALE_JXJ = 45;

  static $AREA_CHARGER = array(
    '广州' => '牟其方',
    '北京' => '任靓晨',
    '上海' => '姜鑫杰',
  );
  static $FIELD_AD_INFO_SALE = array('channel', 'ad_name', 'cid', 'create_time', 'status', 'owner_name', 'quote_rmb', 'url', 'user', 'pwd', 'ad_app_type');
  static $DEPARTMENT = array(
    'Android' => 'Android业务部',
    'iOS' => 'IOS业务部',
    'VIP' => '战略合作部'
  );

  const DEVELOPER_CS_MAIL = 'sheng.chen@dianjoy.com';

  /**
   * 主要查找商务的地区信息
   * @param $id
   * @return array
   */
  public function get_sales_info($id) {
    $DB = $this->get_read_pdo();
    $sql = "SELECT a.`username`,`NAME`,b.`location`,b.`associate`,b.`type`
            FROM `t_admin` AS a
             JOIN `t_sales` AS b ON a.`id`=b.`id`
            WHERE a.`id` = :id";
    $state = $DB->prepare($sql);
    $state->execute(array(
      ':id' => $id,
    ));
    return $state->fetch(PDO::FETCH_ASSOC);
  }

  public function get_user_info($filters) {
    $DB = $this->get_read_pdo();
    $filters['status'] = 1;
    list($conditions, $params) = $this->parse_filter( $filters );
    $sql = "SELECT `id`, `NAME`
            FROM `t_admin`
            WHERE $conditions";
    $state = $DB->prepare($sql);
    $state->execute($params);
    return $state->fetchAll(PDO::FETCH_KEY_PAIR);
  }

  public function get_all_sales() {
    return $this->get_user_info(array(
      'permission' => array(5, 6),
      'status' => self::NORMAL,
    ));
  }


  /**
   * 根据用户的不同取不同的执行人
   * @see http://redmine.dianjoy.com/issues/51089
   *
   * @param $me
   *
   * @return array|null
   */
  public function get_sales_by_me( $me ) {
    $DB = $this->get_read_pdo();
    $sql = "SELECT `type`, `associate`
            FROM `t_sales`
            WHERE `id`=:me";
    $state = $DB->prepare($sql);
    $state->execute(array(':me' => $me));
    $info = $state->fetch(PDO::FETCH_ASSOC);

    if ($info['type'] == self::SALES_ASSISTANT) { // 助理，返回上级商务
      $ids = explode(',', $info['associate']);
      return $this->get_user_info(array('id' => $ids));
    }

    if ($info['type'] == self::SALES_FOLLOW) {
      $ids = explode(',', $info['associate']);
      $ids[] = self::SALE_JXJ;// 跟单商务，加上姜鑫杰
      return $this->get_user_info(array(
        'id' => $ids,
      ));
    }

    if ($info['type'] != self::SALES_FOLLOW && $info['type'] != self::SALES_ASSISTANT) {
      $assistants = $this->get_my_assistants($me);
      return $this->get_user_info(array(
        'id' => $assistants
      ));
    }

    return null;
  }

  /**
   * 根据用户取负责人`owner`和执行人`execute_owner`
   * @see http://redmine.dianjoy.com/issues/51089
   * @param $attr
   * @param $me
   *
   * @return array
   */
  public function get_owner( $attr, $me ) {
    if ($attr['owner']) {
      return array(
        'owner' => $attr['owner'],
        'execute_owner' => $me,
      );
    }

    // 牟其方，任靓晨，刘兆悦三位商务提交广告时，执行人默认为她们的商务助理：李慧杏，宿月，曹静
    if (in_array($me, array(4, 23, 7))) {
      $DB = $this->get_read_pdo();
      // 取所有有助理的人
      $reg = '(^|,)' . $me . '($|,)';
      $sql = "SELECT `id`
            FROM `t_sales`
            WHERE `associate` REGEXP '$reg'";
      $state = $DB->query($sql);
      $associate = $state->fetchColumn();
      if ($associate) {
        return array(
          'owner' => $me,
          'execute_owner' => $associate,
        );
      }
    }


    return array(
      'owner' => $me,
      'execute_owner' => $me,
    );
  }

  public function get_all_sales_location() {
    $DB = $this->get_read_pdo();
    $sql = "SELECT `id`, `location`
            FROM `t_admin`
            WHERE (`permission`=5 OR `permission`=6 OR `id`=1) AND `status`=1";
    return $DB->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR);
  }

  public function get_chargers($id) {
    $DB = $this->get_read_pdo();
    $sql = "SELECT `associate`
            FROM `t_sales`
            WHERE `id` = :id";
    $state = $DB->prepare($sql);
    $state->execute(array(
      ':id' => $id
    ));
    $charger_ids = explode(',', $state->fetchColumn());
    if ($charger_ids) {
      $placeholder = implode(',', array_fill(0, count($charger_ids), '?'));
      $sql = "SELECT a.`id`,`NAME`,b.`location`
              FROM `t_admin` AS a
                JOIN `t_sales` AS b ON a.`id`=b.`id`
              WHERE a.`id` IN ($placeholder)";
      $state = $DB->prepare($sql);
      $state->execute($charger_ids);
      $chargers = $state->fetchAll(PDO::FETCH_ASSOC);
      $result = array();
      foreach ($chargers as $charger) {
        array_push($result, array(
          'key' => $charger['id'],
          'value' => $charger['NAME'],
          'size' => $charger['location'],
          'department' => self::$DEPARTMENT[$charger['location']]
          )
        );
      }
      return $result;
    } else {
      return null;
    }
  }

  public function get_sales_location() {
    $DB = $this->get_read_pdo();
    $sql = "select id,location from t_sales";
    return $DB->query( $sql )->fetchAll(PDO::FETCH_KEY_PAIR);
  }

  public function check_ad_info_for_sale(array $ad_info) {
    $me = $_SESSION['admin_id'];
    $role = $_SESSION['admin_role'];
    $sales = $this->get_sales_location();

    // 以下可以看到全部信息
    if ($me == self::SALE_BOSS // 商务总监
        || !in_array($role, [Admin::SALE, Admin::SALE_MANAGER]) // 非商务
        || $me == $ad_info['owner'] || $me == $ad_info['execute_owner'] // 自己负责的广告
        || in_array($ad_info['owner'], $_SESSION['admin_associate']) // 同组的广告
        || $ad_info['vip_sales'] == $me // 自己负责的 KA 渠道
    ) {
      return $ad_info;
    }

    // 经理看不到渠道号,普通商务看不到更多内容
    $not_same_location = isset($ad_info['location']) && !in_array($ad_info['location'], $_SESSION['admin_location']) || !in_array($sales[$ad_info['owner']], $_SESSION['admin_location']);
    if ($not_same_location && $role != self::SALE_MANAGER) {
      $fields = array('channel', 'owner_name', 'execute_owner_name', 'url', 'user', 'pwd', 'cid', 'full_name', 'company_short', 'owner', 'execute_owner');
      $ad_info = Utils::array_omit($ad_info, $fields);
    } else {
      unset($ad_info['cid']);
    }
    return $ad_info;
  }

  public function get_ad_ops( $ad_ids ) {
    $DB = $this->get_read_pdo();
    $keys = implode(',', array_fill(0, count($ad_ids), '?'));
    $sql = "SELECT `adid`, `NAME`
            FROM `t_ad_operation_log` a JOIN `t_admin` b ON a.`user`=b.`id`
            WHERE `adid` IN ($keys) AND `type`='ad' AND `action`='edit' AND `is_ok`=0
              AND `permission` IN (0,1,7) AND `comment` LIKE '%广告信息修改成功%'
            ORDER BY `datetime`";
    $state = $DB->prepare($sql);
    $state->execute($ad_ids);
    $result = $state->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_COLUMN);
    return array_map(function ($items) {
      return $items[0];
    }, $result);
  }

  public function get_my_manager( $me ) {
    $DB = $this->get_read_pdo();
    $manager = self::SALES_AREA_DIRECTOR;
    $sql = "SELECT a.`id`
            FROM `t_sales` a JOIN `t_sales` b ON a.`location`=b.`location`
            WHERE b.`id`=$me AND a.`type`=$manager";
    return $DB->query($sql)->fetchColumn();
  }

  public function get_my_assistants( $me ) {
    $DB = $this->get_read_pdo();
    $sql = "SELECT id
              FROM `t_sales`
              WHERE FIND_IN_SET(:manager, `associate`)";
    $state = $DB->prepare($sql);
    $state->execute(array(':manager' => $me));
    return $state->fetchAll(PDO::FETCH_COLUMN);
  }

  public function get_user_info_by_id($id) {
    $DB = $this->get_read_pdo();

    $sql = "SELECT `username`,`permission`,`NAME`
            FROM `t_admin`
            WHERE `id`=:id";
    $state = $DB->prepare($sql);
    $state->execute(array(
      ':id' => $id
    ));
    return $state->fetch(PDO::FETCH_ASSOC);
  }

  /**
   * 返回商务对应的运营
   * @param $sale
   * @return string
   */
  public function get_sale_operation( $sale ) {
    $DB = $this->get_read_pdo();
    $sql = "SELECT `operation_manager`
            FROM `t_sales`
            WHERE `id`=:sale";
    $state = $DB->prepare($sql);
    $state->execute(array(
      ':sale' => $sale
    ));
    return $state->fetchColumn();
  }
}