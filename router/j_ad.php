<?php
/**
 * Created by PhpStorm.
 * User: chensheng
 * Date: 2017/9/24
 * Time: 下午1:01
 */
use dianjoy\Macaw\Macaw;

Macaw::options(BASE . 'j_ad/', 'diy\controller\BaseController@on_options');
Macaw::get(BASE . 'j_ad/', 'diy\controller\JADController@get_list');
Macaw::post(BASE . 'j_ad/', 'diy\controller\JADController@create');

Macaw::options(BASE . 'j_ad/(:any)', 'diy\controller\JADController@on_options');
Macaw::delete(BASE . 'j_ad/(:any)', 'diy\controller\JADController@delete');
Macaw::patch(BASE . 'j_ad/(:any)', 'diy\controller\JADController@update');
Macaw::get(BASE . 'j_ad/(:any)', 'diy\controller\JADController@get_ad');

Macaw::get(BASE . 'click/(:any)', 'diy\controller\JADTransferController@getList');
Macaw::options(BASE . 'click/(:any)', 'diy\controller\JADTransferController@on_options');
Macaw::post(BASE . 'click/(:any)', 'diy\controller\JADTransferController@record');