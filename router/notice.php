<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 14/12/23
 * Time: 下午3:38
 */

use dianjoy\Macaw\Macaw;

Macaw::get(BASE . 'notice/', 'diy\controller\NoticeController@get_list');

Macaw::options(BASE . 'notice/(:any)', 'diy\controller\BaseController@on_options');

Macaw::delete(BASE . 'notice/(:any)', 'diy\controller\NoticeController@delete');