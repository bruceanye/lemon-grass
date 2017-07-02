<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 14/11/16
 * Time: 下午3:29
 */
use dianjoy\Macaw\Macaw;

Macaw::get(BASE . 'stat/', 'diy\controller\StatController@get_ad_stat');

Macaw::get(BASE . 'stat/(:any)', 'diy\controller\StatController@get_the_ad_stat');

Macaw::get(BASE . 'stat/(:any)/(:any)', 'diy\controller\StatController@get_ad_daily_stat');

Macaw::get(BASE . 'stat/analyse/', 'diy\controller\StatController@get_daily_stat');

Macaw::get(BASE . 'stat/analyse/daily/(:any)', 'diy\controller\StatController@get_daily_ad');

Macaw::get(BASE . 'stat/export_payment/', 'diy\controller\StatController@export_payment');


Macaw::get(BASE . 'adStat/(:any)/', 'diy\controller\ADStatController@get_stat_list');

Macaw::get(BASE . 'adStat/(:any)/comment/', 'diy\controller\ADStatController@get_stat_comments');

Macaw::get(BASE . 'adStat/(:any)/date/(:any)', 'diy\controller\ADStatController@get_stat_by_date');

Macaw::get(BASE . 'adStat/(:any)/apk/(:any)', 'diy\controller\ADStatController@get_stat_by_apk');

Macaw::get(BASE . 'adStat/(:any)/loc/(:any)', 'diy\controller\ADStatController@get_stat_by_loc');

Macaw::get(BASE . 'adStat/(:any)/hour/(:any)', 'diy\controller\ADStatController@get_stat_by_hour');