<?php
/**
 * Created by PhpStorm.
 * User: chensheng
 * Date: 2017/9/9
 * Time: 下午3:42
 */
use dianjoy\Macaw\Macaw;

Macaw::options(BASE . 'jy_client/', 'diy\controller\BaseController@on_options');
Macaw::get(BASE . 'jy_client/', 'diy\controller\ClientController@get_list');
Macaw::post(BASE . 'jy_client/', 'diy\controller\ClientController@create');