<?php

use dianjoy\Macaw\Macaw;

Macaw::get( BASE . 'ad/', 'diy\controller\CP_ADController@get_list');
Macaw::get( BASE . 'ad/([0-9a-f]{32})', 'diy\controller\CP_ADController@init');

Macaw::get(BASE . 'export/idfa/([a-f0-9]{32})', 'diy\controller\ADController@export_idfa');

Macaw::get(BASE . 'stat/([a-f0-9]{32})', 'diy\controller\StatController@get_the_ad_stat');

Macaw::get(BASE . 'finance/', 'diy\controller\UserController@getMyFinance');

Macaw::get(BASE . 'diy/', 'diy\controller\DiyController@getList');
Macaw::post(BASE . 'diy/', 'diy\controller\DiyController@create');

Macaw::get(BASE . 'diy/([a-f0-9]{32})', 'diy\controller\DiyController@get');
Macaw::patch(BASE . 'diy/([a-f0-9]{32})', 'diy\controller\DiyController@update');
Macaw::delete(BASE . 'diy/([a-f0-9]{32})', 'diy\controller\DiyController@delete');