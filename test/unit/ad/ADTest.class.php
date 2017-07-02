<?php
use diy\service\AD;

/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 16/4/28
 * Time: 下午6:21
 */
class ADTest extends PHPUnit_Framework_TestCase {
  public function testGetADOwnerOperationLog(  ) {
    $service = new AD();
    $start = '2016-03-01';
    $end = '2016-03-31';
    $before_start = '2016-02-01';
    $before_end = '2016-02-29';
    // 先取一条数据
    $sql = "SELECT *
            FROM `t_ad_owner_operation_log`
            WHERE `date`>:start AND `date`<=:end AND `ad_id` NOT IN 
              (
                SELECT `ad_id`
                FROM t_ad_owner_operation_log
                WHERE `date`>:before_start AND `date`<=:before_end
              )
            LIMIT 1";
    /** @var PDO $DB */
    $DB    = require dirname( __FILE__ ) . '/../app/connector/pdo_slave.php';
    $state = $DB->prepare($sql);
    $state->execute([
      ':start' => $start,
      ':end' => $end,
      ':before_start' => $before_start,
      ':before_end' => $before_end,
    ]);
    $case = $state->fetch(PDO::FETCH_ASSOC);

    $ad_id   = $case['ad_id'];
    $ad_info = $service->get_ad_info_by_id( $ad_id );
    list($logs, $owner) = $service->get_ad_owner_operation_log($start, $end, [$ad_id => $ad_info]);

    // 测试正常情况
    $first_key = $start . '_' . $case['date'];
    $last_key = $case['date'] . '_' . $end;
    $this->assertNotEmpty($logs);
    $this->assertArrayHasKey( $first_key, $logs);
    $this->assertArrayHasKey( $last_key, $logs);
    $this->assertEquals($case['origin'], $logs[$first_key][$ad_id]);
    $this->assertEquals($case['new'], $logs[$last_key][$ad_id]);

    // 测试周期内改变后没有再改变的
    $ad_info[$ad_id]['owner'] = $case['new'];
    list($logs, $owner) = $service->get_ad_owner_operation_log($before_start, $before_end, [$ad_id => $ad_info]);
    $this->assertNotEmpty($owner);
    $this->assertArrayHasKey($ad_id, $owner);
    $this->assertEquals($owner[$ad_id], $case['origin']);
  }
}