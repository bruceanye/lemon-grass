<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 15/3/20
 * Time: 下午3:56
 */

require dirname( __FILE__ ) . '/../vendor/autoload.php';

$file = new FileController();
$ipa_path = dirname(__FILE__) . '/../public/upload/ad_url/201503/0_1c539fb91a887cc7b4772e6ea31a1efe.ipa';
$plist_path = dirname(__FILE__) . '/Info.plist';

//$result = $file->parse_ipa($ipa_path, 'test');
$result = $file->parse_plist($plist_path);
echo '======== start ========';
var_dump($result);
echo '======== ok ========';