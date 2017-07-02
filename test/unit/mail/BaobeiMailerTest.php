<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 15/3/21
 * Time: 下午2:54
 */
require dirname( __FILE__ ) . '/../vendor/autoload.php';
require dirname( __FILE__ ) . '/../config/config.php';

use diy\controller\ADController;
use diy\service\AD;
use diy\service\Baobei_Mailer;

class BaobeiMailerTest extends PHPUnit_Framework_TestCase {
  public function testCreate() {
    $mailer = new Baobei_Mailer();
    $service = new AD();
    $_SESSION['id'] = $_SESSION['role'] = 'test';

    $template = 'baobei';
    $ad_id = '946afe0b64e3003890c0325dc7c4c408';

    $info = $service->get_ad_info(array('id' => $ad_id), 0, 1);

    $html = $mailer->create($template, $info);

    file_put_contents('test/mail.html', $html);

    $this->assertNotEmpty($html);
  }

  public function testSend() {
    $mailer = new Baobei_Mailer(true);
    $service = new AD();
    $_SESSION['id'] = $_SESSION['role'] = 'test';

    $ad_id = '946afe0b64e3003890c0325dc7c4c408';

    $info = $service->get_ad_info(array('id' => $ad_id), 0, 1);
    $this->assertTrue($mailer->send('lujia.zhai@dianjoy.com', 'test', $info));
  }
}
