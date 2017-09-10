<?php
/**
 * Created by PhpStorm.
 * User: chensheng
 * Date: 2017/9/10
 * Time: 上午11:14
 */

use dianjoy\Macaw\Macaw;

Macaw::options(BASE . 'j_channel/', 'diy\controller\BaseController@on_options');
Macaw::get(BASE . 'j_channel/', 'diy\controller\JChannelController@get_list');
Macaw::post(BASE . 'j_channel/', 'diy\controller\JChannelController@create');