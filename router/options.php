<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 16/7/15
 * Time: 下午7:09
 */

use dianjoy\Macaw\Macaw;

Macaw::options( BASE . 'diy/', 'diy\controller\BaseController@on_options');

Macaw::options(BASE . 'diy/([a-f0-9]{32})', 'diy\controller\BaseController@on_options');