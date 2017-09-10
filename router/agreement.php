<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 15/5/19
 * Time: 上午10:58
 */

use dianjoy\Macaw\Macaw;

Macaw::options(BASE . 'agreement/(:any)', 'diy\controller\BaseController@on_options');
Macaw::patch(BASE . 'agreement/(:any)', 'diy\controller\AgreementController@renew');
Macaw::get(BASE . 'agreement/', 'diy\controller\AgreementController@get_list');
Macaw::post(BASE . 'agreement/', 'diy\controller\AgreementController@create');