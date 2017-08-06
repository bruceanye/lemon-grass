<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 15/5/19
 * Time: 上午10:58
 */

use dianjoy\Macaw\Macaw;

Macaw::options(BASE . 'channel/', 'diy\controller\BaseController@on_options');
Macaw::post(BASE . 'channel/', 'diy\controller\ChannelController@create');
Macaw::get(BASE . 'channel/', 'diy\controller\ChannelController@get_list');

Macaw::options(BASE . 'channel/(:any)', 'diy\controller\BaseController@on_options');
Macaw::patch(BASE . 'channel/(:any)', 'diy\controller\ChannelController@update');
Macaw::delete(BASE . 'channel/(:any)', 'diy\controller\ChannelController@delete');
Macaw::get(BASE . 'channel/(:any)', 'diy\controller\ChannelController@get_channel_info');

Macaw::get(BASE . 'channel/(:any)/prepaid/', 'diy\controller\ChannelController@get_channel_prepaid');
Macaw::get(BASE . 'channel/(\d+)/feedback/', 'diy\controller\ChannelController@getADs');
Macaw::options(BASE . 'channel/(\d+)/feedback/(:any)', 'diy\controller\BaseController@on_options');
Macaw::patch(BASE . 'channel/(\d+)/feedback/(:any)', 'diy\controller\ChannelController@updateFeedback');

Macaw::options(BASE . 'jy_channel/(:any)/', 'diy\controller\BaseController@on_options');
Macaw::get(BASE . 'jy_channel/list/', 'diy\controller\ChannelController@get_new_list');