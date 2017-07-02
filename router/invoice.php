<?php
/**
 * Created by PhpStorm.
 * User: chensheng
 * Date: 16/3/17
 * Time: 下午4:42
 */
use dianjoy\Macaw\Macaw;

Macaw::options(BASE . 'invoice/', 'diy\controller\BaseController@on_options');

Macaw::get(BASE . 'invoice/', 'diy\controller\InvoiceController@get_list');

Macaw::options(BASE . 'invoice/(:any)', 'diy\controller\BaseController@on_options');

Macaw::post(BASE . 'invoice/(:any)', 'diy\controller\InvoiceController@create');

Macaw::get(BASE . 'invoice/(:any)', 'diy\controller\InvoiceController@init');

Macaw::patch(BASE . 'invoice/(:any)', 'diy\controller\InvoiceController@update');

Macaw::delete(BASE . 'invoice/(:any)', 'diy\controller\InvoiceController@delete');

Macaw::options(BASE . 'invoice/ad/(:any)', 'diy\controller\BaseController@on_options');

Macaw::patch(BASE . 'invoice/ad/(:any)', 'diy\controller\InvoiceController@update_invoice_ad');

Macaw::get(BASE . 'invoice/settle/(:any)', 'diy\controller\InvoiceController@get_transfer_ad');

Macaw::options(BASE . 'invoice/comment/(:any)', 'diy\controller\BaseController@on_options');

Macaw::patch(BASE . 'invoice/comment/(:any)', 'diy\controller\InvoiceController@updateReadComments');