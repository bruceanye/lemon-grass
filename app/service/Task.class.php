<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 15/4/9
 * Time: 下午5:01
 */

namespace diy\service;


use diy\utils\Utils;
use PDO;

class Task extends Base {
  const ON = 1;
  const OFF = 0;

  /**
   * @param $id
   * @param $ad_app_type
   */
  public function create_default_tasks( $id, $ad_app_type ) {
    $tasks = $this->get_default_tasks($ad_app_type);
    foreach ( $tasks as $task ) {
      $this->create_task( $id, $task['name'], $task['desc'], $task['step_rmb'], $task['type'], $task['delta'], $task['param'], $task['probability'] );
    }
  }

  private function get_default_tasks( $ad_app_type = 1) {
    $DB = $this->get_read_pdo();
    $sql = "SELECT `name`, `desc`, `step_rmb`, `type`, `delta`, `param`,
              `probability`
            FROM `t_task_default`
            WHERE `ad_app_type`=:ad_app_type";
    $state = $DB->prepare($sql);
    $state->execute(array(':ad_app_type' => $ad_app_type));
    return $state->fetchAll(PDO::FETCH_ASSOC);
  }

  /**
   * @param $ad_id
   * @param $name
   * @param $desc
   * @param $step_rmb
   * @param $type
   * @param $delta
   * @param $param
   * @param $probability
   */
  private function create_task( $ad_id, $name, $desc, $step_rmb, $type, $delta, $param, $probability ) {
    $DB = $this->get_write_pdo();
    $task_id       = Utils::create_id();
    $now = date('Y-m-d H:i:s');
    $sql = "INSERT INTO `t_task`
            (`id`, `ad_id`, `step_rmb`, `type`, `delta`, `name`, `desc`,
              `create_time`, `status`, `param`, `probability`)
            VALUES (:id, :ad_id, :step_rmb, :type, :delta, :name, :desc,
              '$now', :status, :param, :probability)
            ON DUPLICATE KEY UPDATE `status`=:status, `create_time`='$now',
              `name`=:name, `desc`=:desc, `probability`=:probability";
    $state = $DB->prepare($sql);
    $check = $state->execute(array(
      ':id' => $task_id,
      ':ad_id' => $ad_id,
      ':step_rmb' => $step_rmb,
      ':type' => $type,
      ':delta' => $delta,
      ':name' => $name,
      ':desc' => $desc,
      ':status' => self::ON,
      ':param' => $param,
      ':probability' => $probability,
    ));
    if ( $check ) {
      $log = new ADOperationLogger( $DB );
      $log->log( $ad_id, 'task', 'add', "$step_rmb, $type, $delta, $name, $desc, $param, $probability => $task_id" );
    }
  }
}