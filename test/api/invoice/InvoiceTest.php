<?php
/**
 * Created by PhpStorm.
 * User: chensheng
 * Date: 16/9/5
 * Time: 上午10:19
 */

use api\Friday;
class InvoiceTest extends Friday {
  protected $configJSON = PROJECT_DIR . 'test/api/invoice/invoice.json';
  protected $ad_id;
  protected $session = [
    'ad_id' => 'af4377a5959fb6c7a90d8ee75a3fc062',
    'invoice_id' => '205'
  ];

  public function testAll() {
    $this->doTest();
  }

  protected function validateAPI($test, $api) {
    $api = str_replace('{{ad_id}}', $this->session['ad_id'], $api);
    $api = str_replace('{{invoice_id}}', $this->session['invoice_id'], $api);

    parent::validateAPI($test, $api);
  }
}