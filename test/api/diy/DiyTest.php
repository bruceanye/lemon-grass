<?php
use Meathill\Friday;

/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 16/7/14
 * Time: 下午12:17
 */
class DiyTest extends Friday {
  protected $configJSON = PROJECT_DIR . 'test/api/diy/diy.json';
  
  protected $name = 'diy';
  
  protected $session = [
    'id' => '1719fe39427dccc66990b553af18fca5',
    'role' => 100,
    'channel_id' => 1114,
    'user' => 'song',
  ];
  protected $diy_id;

  public function testAll(  ) {
    $this->doTest();
  }

  protected function setUp() {
    parent::setUp();

    $this->register('POST', 'diy/', 'onPOST_diy');
  }

  protected function validateAPI( $test, $api ) {
    $api = str_replace('{{diy_id}}', $this->diy_id, $api);
    parent::validateAPI( $test, $api );
  }

  public function onPOST_diy($response) {
    $this->diy_id = $response['data']['id'];
  }
}