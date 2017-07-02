<?php
/**
 * Created by PhpStorm.
 * User: chensheng
 * Date: 16/1/6
 * Time: 上午10:49
 */
namespace diy\controller;

use diy\service\Payment;
use diy\utils\Utils;

class PaymentController extends BaseController {
  public function get_list() {
    $me = $_SESSION['id'];
    $start = $_REQUEST['start'];
    $end = $_REQUEST['end'];
    $attr = Utils::array_pick($_REQUEST, array('payment', 'keyword', 'ad_name', 'channel'));
    $filters = array_merge($attr, array(
      'salesman' => $me
    ));

    $payment_service = new Payment();
    $list = $payment_service->get_payment_stat($filters, $start, $end);

    $page = (int)$_REQUEST['page'];
    $page_size = $_REQUEST['pagesize'] ? $_REQUEST['pagesize'] : '20';
    $total = count($list);
    $list = array_slice($list, $page * $page_size, $page_size);

    $this->output(array(
      'code' => 0,
      'msg' => 'get',
      'list' => $list,
      'total' =>$total
    ));
  }
}