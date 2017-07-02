<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 15/8/26
 * Time: 下午2:22
 */

namespace diy\service;

use Exception;
use PDO;
use SQLHelper;

class User extends Base {
  public function has_enough_money(&$attr) {
    $days = (strtotime($attr['end_time']) - strtotime($attr['start_time'])) / 86400 >> 0;
    $attr['total_num'] = $total = $days * (int)$attr['job_num'];
    $cost = $total * (int)$attr['quote_rmb'];
    $DB = $this->get_read_pdo();
    $me = $_SESSION['id'];
    $balance = $this->get_my_balance($DB, $me);
    return $balance >= $cost;
  }

  public function get_full_info() {
    $me = $_SESSION['id'];
    $DB = $this->get_read_pdo();
    $sql = "SELECT a.`username`, `corp`, `NAME` AS `owner`, `email`, `phone`, a.`qq`,
              `balance`, `rmb_out`, `rmb_in`
            FROM `t_diy_user` a LEFT JOIN `t_admin` b ON a.`owner`=b.`id`
            WHERE a.`id`='$me'";
    return $DB->query($sql)->fetch(PDO::FETCH_ASSOC);
  }

  public function getUserFinance(array $filters, $start, $pagesize) {
    list($conditions, $params) = $this->parse_filter($filters);
    $DB = $this->get_read_pdo();
    $sql = "SELECT `id`,`add_time`,`balance`,`reward`,`invoice`,`rmb_ready`
            FROM `t_diy_user_balance_log`
            WHERE $conditions
            ORDER BY `id` DESC
            LIMIT $start, $pagesize";
    $state = $DB->prepare($sql);
    $state->execute($params);
    return $state->fetchAll(PDO::FETCH_ASSOC);
  }

  public function get_all_my_money() {
    $me = $_SESSION['id'];
    $DB = $this->get_read_pdo();
    $balance = $this->get_my_balance( $DB, $me );

    // 取被锁定的钱
    $sql = "SELECT `quote_rmb`, `total_num`
            FROM `t_adinfo_diy` a LEFT JOIN `t_adinfo` b ON a.`id`=b.`id`
            WHERE `status` IN (0,2) AND `create_user`='$me'";
    $locks = $DB->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $lock_money = 0;
    foreach ( $locks as $lock ) {
      $lock_money += (int)$lock['quote_rmb'] * (int)$lock['total_num'];
    }

    return [
      'balance' => (int)$balance,
      'lock' => (int)$lock_money,
    ];
  }

  /**
   * @param $DB
   * @param $me
   *
   * @return array
   */
  private function get_my_balance( PDO $DB, $me ) {
    $sql = "SELECT `balance`
            FROM `t_diy_user`
            WHERE `id`='$me'";
    return $DB->query( $sql )->fetchColumn();
  }

  public function lock_money_for_ad( $quote_rmb, $num ) {
    $me = $_SESSION['id'];
    $DB = $this->get_write_pdo();
    $money = $quote_rmb * $num;
    // 开启事务
    $DB->beginTransaction();
    $sql = "UPDATE `t_diy_user`
          SET `balance`=`balance`-$money
          WHERE `id`='$me' AND `balance`>$money";
    $check = $DB->exec($sql);
    if (!$check) {
      return null;
    }
    $DB->commit();
    return $this->get_all_my_money();
  }

  /**
   * 成功返回余额
   * 失败返回null
   *
   * @param $id string 广告id
   *
   * @return array|null
   */
  public function unlock_money_for_ad( $id ) {
    $me = $_SESSION['id'];
    $DB = $this->get_write_pdo();
    $param = [':id' => $id];
    $sql = "SELECT `quote_rmb`,`total_num`
            FROM `t_adinfo_diy` a LEFT JOIN `t_adinfo` b ON a.`id`=b.`id`
            WHERE `create_user`='$me' AND a.`id`=:id AND `status`=-2
              AND `close_status`=0";
    $state = $DB->prepare($sql);
    $state->execute($param);
    $money = $state->fetch(PDO::FETCH_ASSOC);
    $money = (int)$money['quote_rmb'] * (int)$money['total_num'];

    // 使用事务避免反复提交
    try {
      $DB->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

      $DB->beginTransaction();
      $sql = "UPDATE `t_diy_user`
            SET `balance`=`balance`+$money
            WHERE `id`='$me'";
      $DB->exec($sql);

      $now = date('Y-m-d H:i:s');
      $sql = "UPDATE `t_adinfo_diy`
            SET `close_status`=1, `close_time`='$now'
            WHERE `id`=:id AND `close_status`=0";
      $state = $DB->prepare($sql);
      $state->execute($param);
      $count = $state->rowCount();
      if ($count == 0) { // 说明这条记录之前已经修改过了
        $DB->rollBack();
        return null;
      }

      $check = $DB->commit();
    } catch ( Exception $e) {
      $DB->rollBack();
      return null;
    }

    if ($check) {
      return $this->get_all_my_money();
    } else {
      return null;
    }
  }

  public function update_me( $data ) {
    $me = $_SESSION['id'];
    $DB = $this->get_write_pdo();
    return SQLHelper::update($DB, 't_diy_user', $data, ['id' => $me]);
  }
}