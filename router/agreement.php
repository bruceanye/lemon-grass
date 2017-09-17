<?php
/**
 * Created by PhpStorm.
 * User: chensheng
 * Date: 2017/9/16
 * Time: 下午4:48
 */
use dianjoy\Macaw\Macaw;

Macaw::options(BASE . 'j_agreement/', 'diy\controller\BaseController@on_options');
Macaw::get(BASE . 'j_agreement/', 'diy\controller\JAgreementController@get_list');
Macaw::post(BASE . 'j_agreement/', 'diy\controller\JAgreementController@create');

Macaw::options(BASE . 'j_agreement/(:any)', 'diy\controller\BaseController@on_options');
Macaw::delete(BASE . 'j_agreement/(:any)', 'diy\controller\JAgreementController@delete');
Macaw::patch(BASE . 'j_agreement/(:any)', 'diy\controller\JAgreementController@update');