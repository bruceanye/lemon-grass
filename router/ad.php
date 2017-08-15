<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 14/11/15
 * Time: 下午6:02
 */

use dianjoy\Macaw\Macaw;

Macaw::get(BASE . 'ad/', 'diy\controller\ADController@get_list');

Macaw::get(BASE . 'ad/(:any)', 'diy\controller\ADController@init');

Macaw::get(BASE . 'ad_basic/', 'diy\controller\ADController@get_list_basic');

Macaw::options(BASE . 'ad/(:any)', 'diy\controller\BaseController@on_options');

Macaw::post(BASE . 'ad/(:any)', 'diy\controller\ADController@create');

Macaw::patch(BASE . 'ad/(:any)', 'diy\controller\ADController@update');

Macaw::delete(BASE . 'ad/(:any)', 'diy\controller\ADController@delete');

Macaw::get(BASE . 'ad/(:any)/upload/', 'diy\controller\ADController@get_upload_history');

Macaw::options(BASE . 'ad/(:any)/agreement/(:any)', 'diy\controller\BaseController@on_options');

Macaw::delete(BASE . 'ad/(:any)/agreement/(:any)', 'diy\controller\ADController@deleteAgreement');

Macaw::get(BASE . 'apply/', 'diy\controller\ApplyController@get_list');

Macaw::options(BASE . 'apply/(:any)', 'diy\controller\BaseController@on_options');

Macaw::patch(BASE . 'apply/(:any)', 'diy\controller\ApplyController@update');

Macaw::delete(BASE . 'apply/(:any)', 'diy\controller\ApplyController@delete');

Macaw::get(BASE . 'info/', 'diy\controller\HistoryInfo@get_list');

Macaw::post(BASE . 'baobei/(:any)', 'diy\controller\ADController@resend_baobei_email');

Macaw::get(BASE . 'export/idfa/(:any)', 'diy\controller\ADController@export_idfa');

Macaw::get(BASE . 'competitor_ad/', 'diy\controller\CompetitorAdController@get');

Macaw::options(BASE . 'competitor_ad/(:any)', 'diy\controller\BaseController@on_options');

Macaw::patch(BASE . 'competitor_ad/(:any)', 'diy\controller\CompetitorAdController@update');

Macaw::get(BASE . 'search/', 'diy\controller\ADController@search');


Macaw::get(BASE . 'payment/', 'diy\controller\PaymentController@get_list');

Macaw::get(BASE . 'payment/init_date/', 'diy\controller\PaymentController@init_date');

Macaw::get(BASE . 'payment/init_send_email/', 'diy\controller\PaymentController@init_send_email');


Macaw::get(BASE . 'jy_ad/', 'diy\controller\ADController@get_list_new');

Macaw::options(BASE . 'jy_ad/', 'diy\controller\ADController@on_options');

Macaw::post(BASE . 'jy_ad/', 'diy\controller\ADController@create_new');
