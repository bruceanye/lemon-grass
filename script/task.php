<?php
/**
 * 找到没有深度任务的广告，补充之
 * User: meathill
 * Date: 15/4/20
 * Time: 下午3:50
 */

require dirname(__FILE__) . '/../inc/cm.class.php';
$CM = new CM();
$DB = require dirname(__FILE__) . '/../app/connector/pdo.php';
require dirname(__FILE__) . '/../vendor/autoload.php';

$sql = "SELECT a.`id`,`ad_name`,a.`create_time`, `pack_name`
        FROM `t_adinfo` a LEFT JOIN `t_task` b ON a.`id`=b.`ad_id`
        WHERE a.`status`=0 AND `ad_id` is null
        LIMIT 1";
$ads = $DB->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$insert_sql = "INSERT INTO `t_task`
               (`id`, `ad_id`, `step_rmb`, `type`, `delta`, `name`, `desc`,
                 `create_time`, `status`, `param`, `probability`)
               VALUES ";
$now = date('Y-m-d H:i:s');

foreach ( $ads as $ad ) {
  echo "========\n" .$ad['id'] . "\n";
  $sql = "SELECT `name`, `desc`, `step_rmb`, `type`, `delta`, `param`,
            `probability`, `ad_id`
          FROM `t_adinfo` a LEFT JOIN `t_task` b ON a.`id`=b.`ad_id`
          WHERE `pack_name`=:pack_name AND a.`id`!=:ad_id
            AND b.`status`=1";
  $state = $DB->prepare($sql);
  $state->execute(array(
    ':pack_name' => $ad['pack_name'],
    ':ad_id' => $ad['id'],
  ));
  $tasks = $state->fetchAll(PDO::FETCH_ASSOC);

  if (!$tasks) {
    $service = new diy\service\Task();
    $service->create_default_tasks($ad['id'], 1);

    echo "default\n";

    continue;
  }

  // 只需要某一个广告的
  $ad_id = $tasks[0]['ad_id'];
  foreach ( $tasks as $key => $task ) {
    if ($ad_id != $task['ad_id']) {
      unset($tasks[$key]);
      continue;
    }
  }

  $values = '';
  foreach ( $tasks as $task ) {
    $id = $CM->id1();
    $ad_id = $task['ad_id'];
    $step_rmb = $task['step_rmb'];
    $type = $task['type'];
    $delta = $task['delta'];
    $name = $task['name'];
    $param = $task['param'];
    $probability = $task['probability'];
    $desc = $task['desc'];

    $insert_sql .= "('$id', '$ad_id', $step_rmb, $type, $delta, '$name', '$desc', '$now', 1, '$param', $probability),";
  }

  $DB->exec($insert_sql);
  echo $insert_sql;
  echo $DB->lastInsertId();
}
