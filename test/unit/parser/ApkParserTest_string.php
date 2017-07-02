<?php
use diy\tools\ApkParser;

/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 15/5/21
 * Time: 下午6:47
 */

class ApkParserTest_string extends PHPUnit_Framework_TestCase {
  static $file_name = 'test/DianleSDK.apk';

  public function testUnpackAPK() {
    $parser = new ApkParser(self::$file_name);

    $this->assertTrue(file_exists($parser->manifest));
    $this->assertTrue(file_exists($parser->yml));

    return $parser;
  }

  /**
   * @depends testUnpackAPK
   *
   * @param ApkParser $parser
   */
  public function testGetPermissions(ApkParser $parser) {
    $permissions = array(
      'android.permission.INTERNET',
      'android.permission.GET_TASKS',
      'android.permission.READ_PHONE_STATE',
      'android.permission.ACCESS_NETWORK_STATE',
      'android.permission.WRITE_EXTERNAL_STORAGE',
      'android.permission.ACCESS_WIFI_STATE',
      'android.permission.ACCESS_COARSE_LOCATION',
      'android.permission.ACCESS_FINE_LOCATION',
    );
    $this->assertEmpty(array_diff($permissions, $parser->getPermissions()));
  }

  /**
   * @depends testUnpackAPK
   *
   * @param ApkParser $parser
   */
  public function testGetPackageName(ApkParser $parser) {
    $this->assertEquals('com.dlnetwork.example', $parser->getPackageName());
  }

  /**
   * @depends testUnpackAPK
   *
   * @param ApkParser $parser
   */
  public function testGetVersionName(ApkParser $parser) {
    $this->assertEquals('3.0.5', $parser->getVersionName());
  }
}