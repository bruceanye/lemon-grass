<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 15/3/30
 * Time: 下午5:06
 */

namespace diy\service;

use diy\utils\Utils;
use Exception;
use PDO;
use SQLHelper;

class Job extends Base {
  const OFFLINE = 0;
  const ONLINE = 1;
  const ADD_NUM = 2;
  const RESET_NUM = 3;

  const AT = 'at';
  const EVERY = 'every';

  public static $TABLE = 't_ad_job';
  public static $JOB = 't_job';

  public $daily_param = [
    'is_run'   => 0,
    'jobtype'  => [ 2, 3 ],
    'at_every' => 'every',
    'jobnum'   => [ 'operator' => '>', 'data' => 0 ],
  ];
  public $on_off_param = [
    'is_run' => 0,
    'jobtype' => [0, 1],
    'at_every' => 'at',
    'jobnum' => 0,
  ];

  public function create_job( $ad_id, $type, $at_every, $time, $num = 0, $options = null ) {
    $create_time = date("Y-m-d H:i:s");
    $create_user = $_SESSION['id'];
    $params = '';
    if ($options['keywords']) {
      $params .= '"keywords":"' . $options['keywords'] . '",';
    }
    if ($options['ad_desc']) {
      $params .= '"ad_desc":"' . $options['ad_desc'] . '",';
    }
    if ($params) {
      $params = '{' . substr($params, 0, -1) . '}';
    }

    if ($type < 0 || $type > 4 || $at_every != 'at' && $at_every != 'every') {
      throw new Exception('错误的任务类型', 1);
    }

    if ($at_every == 'every' && $type == 2 && $this->has_daily_job($ad_id)) {
      throw new Exception('该广告已存在每日加分任务', 3);
    }

    if ($create_time > $time) {
      throw new Exception('时间已过', 2);
    }

    $ad = new AD();
    if (!$ad->exist($ad_id)) {
      throw new Exception('不存在ad', 4);
    }

    $id = $this->insert_ad_job([
      'ad_id' => $ad_id,
      'jobtype' => $type,
      'at_every' => $at_every,
      'jobtime' => $time,
      'jobnum' => $num,
      'create_user' => $create_user,
      'create_time' => $create_time,
      'is_run' => -1,
      'params' => $params
    ]);
    if (!$id) {
      throw new Exception('操作失败', 5);
    }

    if ($options['share'] && $type == self::OFFLINE) {
      $this->update_share( $ad_id, $options['show_countdown'] );
    }

    // log it
    $log = new ADOperationLogger();
    $log->log($ad_id, 'job', 'add', "[$type, $at_every, $time, $num] => $id", 0);

    $stamp = strtotime($time);
    $event_url = 'event.php?ajid=' . $id;
    $link_id = $this->insert_job($stamp, $event_url);
    return $this->update_job($link_id, $id);
  }

  private function del_job( $link_id, $id ) {
    $DB = $this->get_write_pdo();
    $DB->exec("DELETE FROM `t_job` WHERE `id`=$link_id");
    $DB->exec("UPDATE `t_ad_job` SET `is_run`=-2 WHERE id=$id");
  }

  public function get_job($filters, $num = 0, $order = null, $method = PDO::FETCH_ASSOC) {
    $DB = $this->get_read_pdo();
    list($conditions, $params) = $this->parse_filter($filters);
    $sql = "SELECT `ad_id`,`id`,`jobtype`,`at_every`,`jobtime`,`is_run`,`jobnum`,`linkid`
            FROM `t_ad_job`
            WHERE $conditions";
    if ($order) {
      $order = $this->get_order($order);
      $sql .= "\n{$order}";
    }
    if ($num > 1) {
      $sql .= "\nLIMIT $num";
    }
    $state = $DB->prepare($sql);
    $state->execute($params);
    if ($num == 1) {
      return $state->fetch($method);
    }
    return $state->fetchAll($method);
  }

  public function get_ad_daily_job($ad_ids) {
    $param = array_merge([
      'ad_id' => $ad_ids,
      'jobtime' => ['operator' => '<', 'data' => date("Y-m-d", time() + 86400 * 2)],
    ], $this->daily_param);
    $result = $this->get_job($param, 0, null, PDO::FETCH_ASSOC | PDO::FETCH_GROUP);
    foreach ( $result as $key => $jobs ) {
      $amount = [
        'jobnum' => 0,
        'jobtime' => ''
      ];
      foreach ( $jobs as $job ) {
        $amount['jobnum'] += (int)$job['jobnum'];
        if ($job['jobtype'] == 2) {
          $amount['jobtime'] = $job['jobtime'];
        }
        if ($job['jobtype'] == 3) {
          $amount['has_more'] = true;
        }
      }
      $result[$key] = $amount;
    }
    return $result;
  }

  public function get_ad_on_off_job( $ad_ids ) {
    $param = array_merge(['ad_id' => $ad_ids], $this->on_off_param);
    return $this->get_job($param, 0, null, PDO::FETCH_ASSOC | PDO::FETCH_UNIQUE);
  }

  public function get_log( array $filters ) {
    $filters['oversea'] = 0;
    $filters['jobtype'] = [self::ADD_NUM, self::RESET_NUM];
    $DB = $this->get_read_pdo();
    list($conditions, $params) = $this->parse_filter( $filters , ['time_field' => 'ctime'] );
    $sql = "SELECT `pack_name`, sum(`jobnum`) AS `rmb`
            FROM `t_ad_job_log` a
              JOIN `t_ad_job` b ON a.`ajid`=b.`id`
              JOIN `t_adinfo` c ON b.`ad_id`=c.`id`
            WHERE $conditions
            GROUP BY `pack_name`";
    $state = $DB->prepare($sql);
    $state->execute($params);
    return $state->fetchAll(PDO::FETCH_KEY_PAIR);
  }

  public function get_to_do( array $filters ) {
    $DB = $this->get_read_pdo();
    $now = time();
    $tomorrow = mktime(0, 0, 0, date('n'), date('j') + 1);
    $type = self::ADD_NUM;
    $filters = array_merge($filters, [
      'jobtype' => $type,
      'a.is_run' => 0,
      'b.stamp' => array(
        array(
          'operator' => '>=',
          'data' => $now,
        ),
        array(
          'operator' => '<',
          'data' => $tomorrow,
        ),
      ),
    ]);
    list($conditions, $params) = $this->parse_filter( $filters );
    $sql = "SELECT a.`ad_id`, SUM(a.`jobrmb`) AS `rmb`, SUM(a.`jobnum`) AS num
            FROM t_ad_job AS a JOIN `t_job` AS b ON a.`linkid`=b.`id`
            WHERE $conditions
            GROUP BY a.`ad_id`";
    $state = $DB->prepare($sql);
    $state->execute($params);
    return $state->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_UNIQUE);
  }

  public function remove_all_job( $id ) {
    $DB = $this->get_write_pdo();
    $jobs = $this->get_job(['ad_id' => $id]);
    $job_id = array();
    foreach ($jobs as $job) {
      $job_id[] = $job['linkid'];
    }
    $job_id = implode(",", $job_id);
    $DB->exec("UPDATE `t_ad_job` SET `is_run`=-2 WHERE `ad_id`='$id'");
    $DB->exec("DELETE FROM `t_job` WHERE `id` IN ($job_id)");
  }

  public function remove_on_off_job( $id, $job_time ) {
    $off_job = $this->get_ad_on_off_job($id);
    $off_job = $off_job ? array_pop($off_job) : $off_job;
    if ($off_job && $off_job['jobtime'] != $job_time) {
      //删除原有的计划任务
      $id = $off_job['id'];
      $this->del_job($off_job['linkid'], $id);

      // log it
      $log = new ADOperationLogger();
      $log->log($id, 'job', 'remove', "jobid: $id", 0);
    }
  }

  private function has_daily_job( $ad_id ) {
    $param = array_merge(['ad_id' => $ad_id], $this->daily_param);
    return $this->get_job( $param, 1);
  }

  private function insert_ad_job( $options ) {
    $DB = $this->get_write_pdo();
    SQLHelper::insert($DB, self::$TABLE, $options);
    return (int)SQLHelper::$lastInsertId;
  }

  private function insert_job( $stamp, $event_url ) {
    $DB = $this->get_write_pdo();
    $attr = [
      'stamp' => $stamp,
      'url' => $event_url,
      'next' => 86400,
      'is_run' => 1,
    ];
    SQLHelper::insert($DB, self::$JOB, $attr);
    return SQLHelper::$lastInsertId;
  }

  protected function parse_filter( array $filters = null, array $options = array() ) {
    $defaults = [
      'to_string' => true,
      'time_field' => 'job_time',
    ];
    $options = array_merge($defaults, $options);
    
    $spec = ['start', 'end'];
    $omit = Utils::array_pick($filters, $spec);
    $left = Utils::array_omit($filters, $spec);
    list($conditions, $params) = parent::parse_filter( $left, ['to_string' => false] );

    foreach ( $omit as $key => $value ) {
      if (!$value) {
        continue;
      }
      switch ($key) {
        case 'start':
          $conditions[] = ' `' . $options['time_field'] . '`>=:start';
          $params[':start'] = $value;
          break;
        
        case 'end':
          $conditions[] = ' `' . $options['time_field'] . '`<:end';
          $params[':end'] = $value;
          break;
      }
    }
    if ($options['to_string']) {
      $conditions = count($conditions) > 0 ? implode(' AND ', $conditions) : 1;
    }
    if ($options['is_append'] && !is_array($conditions) && $conditions) {
      $conditions = ' AND ' . $conditions;
    }
    
    return [$conditions, $params];
  }

  private function update_job( $link_id, $id ) {
    $DB = $this->get_write_pdo();
    $attr = [
      'is_run' => 0,
      'linkid' => $link_id
    ];
    return SQLHelper::update($DB, self::$TABLE, $attr, ['id' => $id]);
  }

  /**
   * @param $ad_id
   * @param $show_countdown
   *
   * @return bool
   */
  private function update_share( $ad_id, $show_countdown ) {
    $DB = $this->get_write_pdo();
    $sql = "UPDATE `t_adinfo_share`
            SET `show_countdown`='$show_countdown'
            WHERE `ad_id`=:id";
    $state = $DB->prepare( $sql );
    return $state->execute([':id' => $ad_id]);
  }
}