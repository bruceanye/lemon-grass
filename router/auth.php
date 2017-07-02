<?PHP
/**
 * 处理用户相关的请求
 * Created by PhpStorm.
 * User: meathill
 * Date: 14/11/13
 * Time: 下午3:04
 */
use dianjoy\Macaw\Macaw;

Macaw::get(BASE . 'auth/', 'diy\controller\AuthController@get_info');

Macaw::options(BASE . 'auth/', 'diy\controller\BaseController@on_options');
Macaw::delete(BASE . 'auth/', 'diy\controller\AuthController@logout');
Macaw::post(BASE . 'auth/', 'diy\controller\AuthController@login');
Macaw::patch(BASE . 'auth/', 'diy\controller\AuthController@update');

Macaw::get(BASE . 'auth/finance/', 'diy\controller\AuthController@get_my_finance');

Macaw::options(BASE . 'user/', 'diy\controller\BaseController@on_options');
Macaw::patch(BASE . 'user/', 'diy\controller\UserController@update');