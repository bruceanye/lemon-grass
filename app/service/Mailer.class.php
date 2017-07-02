<?php
/**
 * Created by PhpStorm.
 * Date: 2014/12/31
 * Time: 22:06
 * @overview 
 * @author Meatill <lujia.zhai@dianjoy.com>
 * @since 
 */

namespace diy\service;

use PHPMailer;
use Mustache_Engine;

class Mailer extends Base {
  private $mail;

  protected $username = 'support@dianjoy.com';
  protected $password = MAIL_PASSWORD;
  protected $from = '点乐自助平台';

  public function errorInfo() {
    return $this->mail->ErrorInfo;
  }

  public function __construct($debug = false) {
    $this->mail = new PHPMailer();
    $this->mail->SMTPDebug = $debug ? 3 : 0;

    $this->mail->isSMTP();
    $this->mail->CharSet = "UTF-8";
    $this->mail->Host = 'smtp.exmail.qq.com';
    $this->mail->SMTPAuth = true;
    $this->mail->Username = $this->username;
    $this->mail->Password = $this->password;
    $this->mail->SMTPSecure = 'ssl';
    $this->mail->Port = 465;

    $this->mail->From = $this->username;
    $this->mail->FromName = $this->from;
    $this->mail->isHTML(true);
  }

  public function addAttachment($path, $name) {
    return $this->mail->addAttachment($path, $name);
  }

  public function create( $template, $data = null, $extra = null ) {
    $content = file_get_contents(PROJECT_DIR . '/template/email/' . $template . '.html');
    if (is_array($data) || is_array($extra)) {
      $data = array_merge((array)$data, (array)$extra);
      $m = new Mustache_Engine(array('cache' => '/var/tmp'));
      $content = $m->render($content, $data);
    }
    return $content;
  }

  public function send($to, $subject, $content, $cc = null) {
    if (!is_array($to) && filter_var($to, FILTER_VALIDATE_EMAIL)) {
      $to = [ $to ];
    }
    if (is_string($cc) && filter_var($cc, FILTER_VALIDATE_EMAIL)) {
      $cc = [$cc];
    }
    $this->mail->clearAddresses();
    foreach ($to as $address) {
      $this->mail->addAddress($address);
    }
    if (is_array($cc)) {
      foreach ( $cc as $address ) {
        $this->mail->addCC($address);
      }
    }
    $this->mail->Subject = $subject;
    $this->mail->Body = $content;

    return $this->mail->send();
  }
}