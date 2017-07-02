<?php

/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 16/6/22
 * Time: 下午6:27
 */
namespace api\ad;

use Meathill\Friday;

class ADTest extends Friday {
  protected $configJSON = PROJECT_DIR . 'test/api/ad/ad.json';

  protected $name = 'AD';

  public function testAll() {
    parent::doTest();
  }
}