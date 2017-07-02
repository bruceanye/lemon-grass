<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 16/6/29
 * Time: 下午6:06
 */

namespace api\ad;


use Meathill\Friday;

class iOS_CP_ADTest extends Friday {
  protected $configJSON = PROJECT_DIR . 'test/api/cp/ios-cp.json';
  
  protected $name = 'CP_AD';

  protected $session = [
    'id' => '1719fe39427dccc66990b553af18fca5',
    'role' => 100,
    'channel_id' => 1114,
    'user' => 'song',
    'type' => 2,
  ];

  public function testAll() {
    $this->doTest();
  }
}