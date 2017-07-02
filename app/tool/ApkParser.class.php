<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 15/5/21
 * Time: 下午5:11
 */

namespace diy\tools;


use Exception;

/**
 * @property string manifest
 * @property string yml
 * @property boolean has_sm
 */
class ApkParser {
  public static $MANIFEST = 'AndroidManifest.xml';
  public static $YML = 'apktool.yml';

  public static $default_config = array(
    'tmp_dir' => '/tmp/lm-apk/',
  );
  protected static $refer_reg = '/^@(\w+)\/([\w_]+)$/';

  private $config;
  private $yml_data;
  private $xml_data;

  protected $version;
  protected $res;
  protected $app_name;
  protected $has_sm;

  public function __construct($apk_file, $config = null) {
    $filename = $this->getFileName($apk_file);
    $this->config = array_merge(self::$default_config, (array)$config);
    $this->output_dir = $this->config['tmp_dir'] . $filename . '/';

    // 解压
    $cmd = "/usr/local/bin/apktool d $apk_file -s -f -p " . $this->config['tmp_dir'] . " -o " . $this->output_dir . " 2>&1";
    $output = shell_exec($cmd);

    if (strpos($output, 'Exception in thread') !== false) {
      throw new Exception('Apktool解析包失败', 10);
    }

    // 读取xml和yml
    if (file_exists($this->manifest)) {
      $this->xml_data = simplexml_load_file($this->manifest);
    }
    if (file_exists($this->yml)) {
      $this->yml_data = $this->readYML($this->yml);
    }
    // 是否包含数盟SDK
    $this->has_sm = file_exists($this->output_dir . 'assets/cn.shuzilm.config.json')
      || file_exists($this->output_dir . 'lib/armeabi/libdu.so');
  }

  public function __get($key) {
    switch ($key) {
      case 'manifest':
        return $this->output_dir . self::$MANIFEST;
        break;

      case 'yml':
        return $this->output_dir . self::$YML;
        break;

      case 'has_sm':
        return $this->has_sm;
        break;

      default:
        throw new Exception('no attribute', 1);
        break;
    }
  }

  public function clear() {
    $result = shell_exec('rm -rf ' . $this->output_dir . ' 2>&1');
    return $result;
  }

  public function getAppName() {
    if (!$this->app_name) {
      $applications = $this->xml_data->{'application'};
      foreach ( $applications as $application ) {
        $this->app_name = $application->attributes('android', true)->label;
      }
      $this->app_name = $this->check($this->app_name);
    }
    return $this->app_name;
  }

  /**
   * @return array
   */
  public function getPermissions() {
    $permissions = $this->xml_data->{'uses-permission'};

    $result = array();
    foreach ($permissions as $permission) {
      $result[] = $permission->attributes('android', true)->__toString();
    }
    return $result;
  }

  /**
   * @return string
   */
  public function getPackageName() {
    if (!$this->xml_data) {
      return '';
    }
    $pack_name = $this->xml_data->attributes()['package']->__toString();
    return $pack_name;
  }

  /**
   * @return string
   */
  public function getVersionName() {
    if (!$this->version) {
      $this->version = $this->yml_data['versionInfo']['versionName'];
      $this->version = $this->check($this->version);
    }
    return $this->version;
  }

  private function getFileName( $apk_file ) {
    $start = strrpos($apk_file, '/');
    $end = strrpos($apk_file, '.');
    return substr($apk_file, $start + 1, $end - $start - 1);
  }

  private function readYML($file) {
    $content = file_get_contents($file);
    $lines = preg_split('/[\r\n]+/', $content);
    $yml = array();
    $keys = array();
    foreach ( $lines as $line ) {
      if (!preg_match('/\w/', $line)) {
        continue;
      }
      $count = 0;
      $key = $value = null;
      if (strpos($line, ':') === false) {
        preg_match('/^(\s+)?(.*)$/', $line, $matches);
        $tabs = strlen($matches[1]) >> 1;
        $value = $matches[2];
      } else {
        $line_pair = explode(': ', $line);
        // 取缩进
        preg_match('/^(\s+)?([\w\-\.\/]+)/', $line_pair[0], $matches);
        $key = $matches[2];
        $value = preg_replace('/^[\'\"]/', '', $line_pair[1]);
        $value = preg_replace('/[\'\"]$/', '', $value);
        $tabs = (int)strlen($matches[1]) >> 1;
      }

      if (is_numeric($value) || is_numeric(preg_replace('/\s/', '', $value))) {
        $value = preg_replace('/\s/', '', $value) + 0;
      }
      if ($value === 'true') {
        $value = true;
      }
      if ($value === 'false') {
        $value = false;
      }

      $root = &$yml;
      while ($count < $tabs) {
        $root = &$yml[$keys[$count]];
        $count++;
      }
      if ($key) {
        $keys[$tabs] = $key;
        $root[$key] = $value;
      } else {
        $root[$keys[$count]] = $value;
      }
    }
    return $yml;
  }

  /**
   * 有些apk会把版本放在资源XML里，这个函数用来转换
   *
   * @param $version
   *
   * @return mixed
   */
  private function check( $version ) {
    if (preg_match(self::$refer_reg, $version, $matches)) {
      if (!$this->res || !$this->res[$matches[1]]) {
        $xml = $this->output_dir . 'res/values/' . $matches[1] . 's.xml';
        $this->res = is_array($this->res) ? $this->res : array();
        $this->res[$matches[1]] = $this->parse_res($xml);
      }
      $version = $this->res[$matches[1]][$matches[2]];
    }
    return $version;
  }

  private function parse_res( $xml ) {
    $xml = simplexml_load_file($xml);
    $children = $xml->children();
    $array = array();
    foreach ( $children as $child ) {
      $array[$child->attributes()['name']->__toString()] = $child->__toString();
    }
    return $array;
  }
}