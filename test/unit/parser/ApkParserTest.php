<?php
use diy\tools\ApkParser;

/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 15/5/21
 * Time: 下午6:47
 */

class ApkParserTest extends PHPUnit_Framework_TestCase {
  static $file_name = 'test/popo_v1.2.0_003_signed.apk';

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
      'android.permission.VIBRATE',
      'android.permission.RECEIVE_BOOT_COMPLETED',
      'android.permission.WRITE_SETTINGS',
      'android.permission.DISABLE_KEYGUARD',
      'android.permission.ACCESS_COARSE_LOCATION',
      'android.permission.ACCESS_WIFI_STATE',
      'android.permission.VIBRATE',
      'android.permission.GET_TASKS',
      'android.permission.INTERNET',
      'android.permission.SYSTEM_ALERT_WINDOW',
      'android.permission.ACCESS_NETWORK_STATE',
      'android.permission.READ_PHONE_STATE',
      'android.permission.WRITE_EXTERNAL_STORAGE',
      'android.permission.ACCESS_FINE_LOCATION',
      'com.android.launcher.permission.INSTALL_SHORTCUT',
      'com.android.launcher.permission.READ_SETTINGS',
    );
    $this->assertEmpty(array_diff($permissions, $parser->getPermissions()));
  }

  /**
   * @depends testUnpackAPK
   *
   * @param ApkParser $parser
   */
  public function testGetPackageName(ApkParser $parser) {
    $this->assertEquals('com.paopao.gamebaike', $parser->getPackageName());
  }

  /**
   * @depends testUnpackAPK
   *
   * @param ApkParser $parser
   */
  public function testGetVersionName(ApkParser $parser) {
    $this->assertEquals('1.2.0', $parser->getVersionName());
  }
}