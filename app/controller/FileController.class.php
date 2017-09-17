<?php

/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 15/1/22
 * Time: 下午4:17
 */

namespace diy\controller;

use CFPropertyList\CFPropertyList;
use CFPropertyList\CFType;
use diy\model\ADModel;
use diy\service\AD;
use diy\service\Enemy;
use diy\service\FileLog;
use diy\tools\ApkParser;
use diy\utils\Utils;
use Exception;
use ZipArchive;

class FileController extends BaseController {
  //定义上传文件路径，相对路径，根目录
  public static $up_path=array(
    //backinfo 上传文件路径（截图和日志）
    'usererr'=>'upload/usererr/',
    //addapp 上传应用图标路径
    'appicon'=>'upload/appicon/',
    //updateuser 上传开发者身份证扫描件路径
    'identity'=>'upload/identity/',
    //updateuser 上传开发者身份证扫描件临时路径
    'identity_tmp'=>'upload/identity/tmp/',
    //free_tax_apply 媒介为开发者申请免税的手持身份证照片
    'identity_pic_hand'=>'upload/identity_pic_hand/',
    //addad 上传新建广告应用
    'ad_url'=>'upload/ad_url/',
    //addad 上传新建广告logo
    'pic_path'=>'upload/ad_url/',
    //addad 上传新建广告截图
    'ad_shoot'=>'upload/ad_url/',
    // 原生广告截图
    'banner_url' => 'upload/banner/',
    // 广告视频
    'ad_video' => 'upload/ad_video/',
    // 大尺寸广告图
    'ad_large_shoot' => 'upload/ad_large_shoot/',
    // banner图
    'banner_img' => 'upload/banner_img/',
    //app 上传自定义广告墙皮肤
    'list_bg'=>'upload/list_bg/',
    //app 上传应用，审核用
    'upload_app'=>'upload/upload_app/',
    //app 上传应用截图，审核用
    'app_pic'=>'upload/app_pic/',
    //business_license 下游渠道上传营业执照
    'business_license' => 'upload/business_license/',
    //渠道管理员后台广告的物料包
    'material_url' => 'upload/material_url/',
    // 锁屏广告图
    'lock_img' => 'upload/lock_img/',
    // 合同的PDF文件
    'agreement_pdf' => 'upload/agreement_pdf/',
  );

  protected $need_auth = false;

  private $radar_map = array(
    'pack_name' => 'packagename',
    'ad_name' => 'app_name',
    'label' => 'app_category',
    'pic_path' => 'icon_path',
    'ad_size' => 'file_size',
    'ad_lib' => 'app_versioncode',
    'ad_shoot' => 'screenshots',
    'ad_desc' => 'memo',
  );
  private $id = '';

  public function fetch() {
    $file = trim($_POST['file']);
    $type = isset($_REQUEST['name']) ? $_REQUEST['name'] : 'ad_url';
    $id = isset($_REQUEST['id']) && $_REQUEST['id'] != '' && $_REQUEST['id'] != 'undefined' ? $_REQUEST['id'] : $this->create_id();

    // 过滤不抓取的情况
    if (preg_match('/itunes\.apple\.com/', $file)) {
      $this->output(array(
        'code' => 1,
        'msg' => '暂时不支持抓取iTunes内容，相关功能开发中。',
      ));
    }

    $result = array(
      'code' => 0,
      'form' => array(),
      'id' => $id,
    );
    // 已经在我们的机器上了，直接分析
    $path = $filename = '';
    if (preg_match(LOCAL_FILE, $file)) {
      $result['msg'] = 'exist';
      $path = preg_replace(LOCAL_FILE, '', $file);
    } else {
      try {
        $content = @file_get_contents($file);
        if (!$content) {
          $error = $this->parse_error($http_response_header);
          $this->exit_with_error(10, $error, 403);
        }
        $filename = $this->parse_filename($file, $http_response_header);
        $path = $this->get_file_path($type, $filename, $id);
        file_put_contents(UPLOAD_BASE . $path, $content);
        // 生成反馈
        $result['msg'] = 'fetched';
      } catch (Exception $e) {
        $this->exit_with_error(2, '找不到目标文件，无法完成抓取。', 404);
      }
    }

    // 记录到log里
    $service = new FileLog();
    $service->insert_fetch_log($id, $type, $path, $file, $filename);

    if (preg_match('/\.apk$/i', $path)) {
      $package = $this->parse_apk($path);
      $result = array_merge($result, $package);
    }
    if (preg_match('/\.ipa$/i', $path)) {
      $package = $this->parse_ipa($path);
      $result = array_merge($result, $package);
    }
    $result['form']['ad_url'] = UPLOAD_URL . $path;
    $result['form']['id'] = $id;

    $this->output($result);
  }

  public function upload() {
    $file = $_FILES['file'];
    if (!$file) {
      $this->exit_with_error(1, '无法获取文件，请检查服务器设置。', 400);
    }

    $id = $this->id = isset($_REQUEST['id']) && $_REQUEST['id'] != '' && $_REQUEST['id'] != 'undefined' ? $_REQUEST['id'] : $this->create_id();
    $type = isset($_REQUEST['name']) ? $_REQUEST['name'] : 'ad_url';
    $file_name = $file['name'];
    $md5 = $_REQUEST['md5'];

    $file_md5 = md5_file($file['tmp_name']);
    if ($md5 && $md5 != $file_md5) {
      $this->exit_with_error(2, '文件MD5不一致，上传失败', 408);
    }

    $new_path = $this->get_file_path( $type, $file_name, $id );

    //对管理员和广告主后台上传的图片文件自动压缩
    if ($type == 'pic_path') {
      $this->resize_image( $new_path, $file, 128, 128 );
    }
    if ($type == 'ad_shoot') {
      $this->resize_image( $new_path, $file, 0, 400);
    }

    move_uploaded_file($file['tmp_name'], UPLOAD_BASE . $new_path);

    // 记录到log里
    $service = new FileLog();
    $service->insert($id, $type, $new_path, $file_name);

    // 生成反馈
    $result = array(
      'code' => 0,
      'msg' => 'uploaded',
      'id' => $id,
      'url' => UPLOAD_URL . $new_path,
      'form' => array(),
    );

    if (preg_match('/\.apk$/i', $new_path)) {
      $package = $this->parse_apk($new_path);
      $result = array_merge($result, $package);
    }
    if (preg_match('/\.ipa$/i', $new_path)) {
      $package = $this->parse_ipa($new_path);
      $result = array_merge($result, $package);
    }
    $result['form']['id'] = $id;
    $result['form']['pack_md5'] = $file_md5;

    $this->output($result);
  }

  public function parse_ipa( $path ) {
    $full_path = UPLOAD_BASE . $path;
    $zip = new ZipArchive();
    if ($zip->open($full_path)) {
      $filename = '';
      for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (preg_match('/Payload\/(.+)?\.app\/Info.plist$/i', $name)) {
          $filename = $name;
          break;
        }
      }
      if (!$filename) {
        $this->exit_with_error(20, '无法解析IPA文件', 406);
      }
      $plist = $zip->getFromName($filename);
      $tmp_path = '/tmp/' . $this->id . '.plist';

      // 写在临时文件夹里
      file_put_contents($tmp_path, $plist);

      $form = $this->parse_plist( $tmp_path );
      $form['ad_size'] = Utils::format_file_size( filesize( $full_path ) );

      // 从数据库读相同包名的广告来补充数据
      if ( $form['pack_name'] ) {
        $ad_service = new AD();
        $info       = $ad_service->get_ad_info_by_pack_name( $form['pack_name'], ADModel::IOS );
        if ($info) {
          $info = $this->addPrefixToAssets( $info );
          $form = array_merge($form, $info);
        }
      }
      
      unlink($tmp_path);

      return array(
        'form' => $form,
      );
    };
    return array('error' => '解压失败');
  }

  private function addPreFix( $pic_path ) {
    if (!$pic_path || preg_match( '/^(https?:)?\/\//', $pic_path )) {
      return $pic_path;
    }
    return UPLOAD_URL . $pic_path;
  }

  /**
   * 存在库中的文件地址不包含其实路径，通过这个函数补充
   *
   * @param $info
   *
   * @return mixed
   */
  private function addPrefixToAssets( $info ) {
    $info['ad_shoot'] = implode( ',', array_map( function ( $url ) {
      return $this->addPreFix($url);
    }, explode( ',', $info['ad_shoot'] ) ) );
    $info['pic_path'] = $this->addPreFix($info['pic_path']);

    return $info;
  }

  /**
   * @return string
   */
  private function create_id() {
    return md5(uniqid());
  }

  /**
   * @return string
   */
  private function create_id_8() {
    return substr($this->create_id(), 24, 8);
  }

  /**
   * @param $type
   * @param $file_name
   * @param $id
   *
   * @return string
   */
  private function get_file_path( $type, $file_name, $id ) {
    $path = isset( self::$up_path[ $type ] ) ? self::$up_path[ $type ] : 'upload/';
    $dir = $path . date( "Ym" ) . '/';
    if ( ! is_dir( UPLOAD_BASE . $dir ) ) {
//      mkdir( UPLOAD_BASE . $dir, 0777, true );
    }
    $ext = substr( $file_name, strrpos( $file_name, '.' ) );
    if ( strpos( $ext, 'php' ) !== false ) {
      $ext = '.ban';
    }

    $index = $this->create_id_8();
    $new_path = $dir . $index . '_' . $id . $ext;
    while ( file_exists( UPLOAD_BASE . $new_path ) ) {
      $index = $this->create_id_8();
      $new_path = $dir . $index . '_' . $id . $ext;
    }

    return $new_path;
  }

  private function get_permission_id( $permissions ) {
    $service = new AD();
    $all = $service->get_all_permissions();
    $result = array();
    foreach ( $all as $permission ) {
      if (in_array($permission['name'], $permissions)) {
        $result[] = $permission['id'];
      }
    }
    return $result;
  }

  /**
   * @param $path
   * @param $suffix
   *
   * @return string
   */
  private function get_resize_path( $path, $suffix ) {
    $offset = strrpos($path, '.');
    return substr($path, 0, $offset) . $suffix . substr($path, $offset);
  }

  private function http_build_url( $parts ) {
    $url = '';
    foreach ( $parts as $key => $part ) {
      switch ($key) {
        case 'port':
          $url .= ':';
          break;
      }
      $url .= $part;
      switch ($key) {
        case 'scheme':
          $url .= '://';
          break;
      }
    }
    return $url;
  }

  /**
   * @param $path
   *
   * @return array
   */
  private function parse_apk( $path ) {
    $full_path = UPLOAD_BASE . $path;
    try {
      $apk = new ApkParser($full_path);
    } catch (Exception $e) {
      return array(
        'form' => array(
          'ad_size' => Utils::format_file_size(filesize($full_path)),
        ),
      );
    }

    $permission = $apk->getPermissions();
    $package = array(
      'pack_name' => $apk->getPackageName(),
      'ad_lib'    => $apk->getVersionName(),
      'ad_size'   => Utils::format_file_size( filesize( $full_path ) ),
      'permission' => $this->get_permission_id(array_keys($permission)),
    );

    // 从数据库读相同包名的广告有哪些可以直接用的数据
    $ad_service = new AD();
    $info = $ad_service->get_ad_info_by_pack_name($package['pack_name']);
    if (!$info && !defined('DEBUG')) { // 没有同包名的广告，再试试应用雷达
      try {
        $info = json_decode(file_get_contents('http://192.168.0.165/apk_info.php?pack_name=' . $package['pack_name']), true);
      } catch (Exception $e) {

      }
      if ($info) {
        foreach ( $this->radar_map as $key => $value ) {
          $info[$key] = $info[$value];
        }
      }
    }
    if ($info) {
      $info = $this->addPrefixToAssets( $info );
    }

    // 记录不良SDK
    if ($apk->has_sm) {
      $enemy = new Enemy();
      $enemy->log_sm($this->id, $path, $package['pack_name'], $apk->getAppName());
    }
    
    // 释放硬盘空间
    $apk->clear();

    return array(
      'form'       => array_merge( (array) $info, $package ),
    );
  }

  private function parse_error( $http_response_header ) {
    $http_reg = '/^http\/1.1 (\d+) ([\w\s]+)/i';
    $matches = [];
    foreach ( $http_response_header as $response ) {
      $is_http = preg_match($http_reg, $response, $matches);

      if ($is_http) {
        break;
      }
    }
    if ($matches[1] == '404') {
      return '该链接无效，对象不存在。请核对后再试。';
    }
    if ($matches[1] >= 500) {
      return '抓取目标服务器出错，无法完成抓取。';
    }
    return '抓取失败，错误不明。您可以稍后重试。';
  }

  /**
   * 从一串HTTP响应头里分析文件名称
   *
   * @param $url
   * @param $http_response_header
   *
   * @return string
   */
  private function parse_filename( $url, $http_response_header ) {
    $location_reg = '/^Location: (\S+)/i';
    $content_reg = '/^Content-Disposition: \w+; ?filename=("?)(.*)\1/i';
    foreach ( $http_response_header as $response ) {
      $matches = array();

      // 还是跳转后的url？
      $is_location = preg_match($location_reg, $response, $matches);
      if ($is_location) {
        $url = $matches[1];
        continue;
      }

      // 或者是包含文件名的什么东西？
      $is_disposition = preg_match($content_reg, $response, $matches);
      if ($is_disposition) {
        $url = $matches[2];
      }
    }
    // 过滤掉url后面可能的参数
    $parts = parse_url($url);
    if ($parts['query'] || $parts['fragment']) {
      unset($parts['query']);
      unset($parts['fragment']);
      $url = $this->http_build_url($parts);
    }
    return $url;
  }

  /**
   * @param string $path
   *
   * @return array
   */
  private function parse_plist( $path ) {
    $plist = new CFPropertyList( $path, CFPropertyList::FORMAT_BINARY );
    $dict  = $plist->getValue( 'CFDictionary' );
    $form  = array(
      'ad_name'      => $dict->get( 'CFBundleDisplayName' ),
      'ad_lib'       => $dict->get( 'CFBundleShortVersionsString' ),
      'pack_name'    => $dict->get( 'CFBundleIdentifier' ),
      'process_name' => $dict->get( 'CFBundleExecutable' ),
    );
    foreach ( $form as $key => $value ) {
      if ( $value instanceof CFType ) {
        $form[ $key ] = $value->getValue();
      }
      if ( $key == 'ad_lib' && ! $value ) {
        $ad_lib       = $dict->get( 'CFBundleVersion' );
        $form[ $key ] = $ad_lib instanceof CFType ? $ad_lib->getValue() : '';
      }
    }

    $urls        = $dict->get( 'CFBundleURLTypes' );
    $url_schemes = array();
    if ( $urls ) {
      $urls = $urls->toArray();
      foreach ( $urls as $url ) {
        $url_schemes[] = $url['CFBundleURLSchemes'];
      }
    }
    $form['url_type'] = implode( ';', Utils::array_flatten( $url_schemes ) );

    return $form;
  }

  /**
   * @param string $path
   * @param string $file
   * @param int $width
   * @param int $height
   *
   * @return boolean
   */
  private function resize_image( $path, $file, $width = 0, $height = 0 ) {
    $path = UPLOAD_BASE . $path;
    $is_png = preg_match('/\.png$/', $path);
    $image = $is_png ? imagecreatefrompng( $file['tmp_name'] ) : imagecreatefromjpeg( $file['tmp_name'] );
    list( $width_origin, $height_origin ) = getimagesize( $file['tmp_name'] );
    if ($width == 0 || $height == 0) {
      $suffix = '_' . ($width ? 'w' : 'h') . '_' . ($width ? $width : $height);
    } else {
      $suffix = "_{$width}_{$height}";
    }
    $path = $this->get_resize_path($path, $suffix);
    $width = $width ? $width : $width_origin;
    $height = $height ? $height : $height_origin;

    if ( $width_origin != $width || $height_origin != $height ) {
      $canvas = imagecreatetruecolor( $width, $height );
      imagealphablending( $canvas, false );
      imagesavealpha( $canvas, true );
      if ( $width_origin > $height_origin ) {
        imagecopyresampled( $canvas, $image, 0, 0, (int) ( $width_origin - $height_origin ) / 2, 0, $width, $height, $height_origin, $height_origin );
      } else {
        imagecopyresampled( $canvas, $image, 0, 0, 0, (int) ( $height_origin - $width_origin ) / 2, $width, $height, $width_origin, $width_origin );
      }
      if ( $is_png ) {
        imagepng( $canvas, $path );
      } else {
        imagejpeg( $canvas, $path );
      }
      imagedestroy( $canvas );
    } else {
      imagealphablending( $image, false );
      imagesavealpha( $image, true );
      if ( $is_png ) {
        imagepng( $image, $path );
      } else {
        imagejpeg( $image, $path );
      }
    }

    return true;
  }
}