<?php
use diy\service\DailyStat;

/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 16/4/27
 * Time: 下午6:39
 */
class DailyStatTest extends PHPUnit_Framework_TestCase {
  public function testGetData () {
    /** @var Redis $redis */
    $redis   = require dirname( __FILE__ ) . '/../../../app/connector/redis.php';
    $start   = '2016-05-01';
    $end     = '2016-05-31';
    $key     = "daily_stat_{$start}_{$end}" . (DEBUG ? '_debug' : '');
    $redis->del($key);
    $service = new DailyStat();
    list($total, $list) = $service->get_data($start, $end);

    $this->assertNotEmpty($list);
    $this->assertTrue($total['count'] <= count($list));
    
    $sql = "SELECT COUNT('x')
            FROM `s_daily_stat_{$start}_{$end}_debug`
            WHERE 1";
    /** @var PDO $DB */
    $DB    = require dirname( __FILE__ ) . '/../../../app/connector/pdo_daily.php';
    $count = $DB->query($sql)->fetchColumn();
    $this->assertEquals(count($list), $count);
    
    $sql = "SELECT COUNT('X')
            FROM `s_daily_stat_{$start}_{$end}_debug`
            WHERE `owner`='' OR `owner` IS NULL";
    $count = $DB->query($sql)->fetchColumn();
    $this->assertEquals(0, $count);

    $sql = "SELECT SUM(`transfer`) AS `transfer`,SUM(`cpa`) AS `cpa`
            FROM `s_daily_stat_{$start}_{$end}_debug`
            WHERE 1";
    $result = $DB->query($sql)->fetch(PDO::FETCH_ASSOC);
    $this->assertEquals($total['transfer'], $result['transfer']);
    $this->assertEquals($total['cpa'], $result['cpa']);
  }
}