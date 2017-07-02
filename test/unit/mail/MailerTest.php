<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 15/3/21
 * Time: 下午2:54
 */

use diy\controller\ADController;
use diy\service\AD;
use diy\service\Mailer;

class MailerTest extends PHPUnit_Framework_TestCase {
  public function testCreate() {
    $mail = new Mailer(true);
    $service = new AD();
    $_SESSION['id'] = $_SESSION['role'] = 'test';
    $controller = new ADController();

    $template = 'baobei';
    $ad_id = 'a8b13c843a8f2c598381f9dfe6eec16b';

    $info = $service->get_ad_info(array('id' => $ad_id), 0, 1);
    $info = $controller->translate($info);

    $html = $mail->create($template, $info);

    file_put_contents('test/mail.html', $html);

    $this->assertNotEmpty($html);
    $this->assertTrue($mail->send('lujia.zhai@dianjoy.com', 'test', $html));
  }
}
