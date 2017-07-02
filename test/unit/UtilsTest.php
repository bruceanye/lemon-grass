<?php
use diy\utils\Utils;

/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 16/6/22
 * Time: 下午5:44
 */
class UtilsTest extends PHPUnit_Framework_TestCase {
  public function testArrayOmit() {
    $arr = [0, 1, 2, 3, 4, 5];
    $result = Utils::array_omit($arr, [1, 2]);
    $this->assertArrayNotHasKey(1, $result);
    $this->assertArrayNotHasKey(2, $result);

    $arr = [
      'abc' => 'a',
      '0' => 'b',
      5 => 'c',
      'b' => 'd',
    ];
    $result = Utils::array_omit($arr, 'abc', 'c');
    $this->assertArrayNotHasKey('abc', $result);
    $this->assertArrayHasKey('0', $result);
    $this->assertArrayHasKey(5, $result);
    $this->assertArrayHasKey('b', $result);
  }
}