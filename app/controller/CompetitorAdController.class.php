<?php
/**
 * Created by PhpStorm.
 * User: hemengyuan
 * Date: 15/5/20
 * Time: 下午3:41
 */

namespace diy\controller;

use diy\model\CompetitorAdModel;
use diy\service\CompetitorAd;
use Exception;

class CompetitorAdController extends BaseController {

  public function update($pack_name) {
    $attr = $this->get_post_data();
    $init = array(
      'pack_name' => $pack_name,
    );
    $options = array(
      'idAttribute' => 'pack_name',
    );
    $ad_model = new CompetitorAdModel($init, $options);

    try {
      $ad_model->update($attr);
    } catch (Exception $e) {
      $http_code = $e->getCode() == '100' ? 403 : 500;
      $this->exit_with_error($e->getCode(), $e->getMessage(), $http_code);
    }

    $this->output(array(
      'code' => 0,
      'msg' => '修改完成',
      'ad' => $attr,
    ));
  }

  public function get() {
    $order = $_REQUEST['order'];
    $seq = $_REQUEST['seq'];
    $page = (int)$_REQUEST['page'];
    $page_size = $_REQUEST['page_size'] ? $_REQUEST['page_size'] : 20;
    $keyword = trim($_REQUEST['keyword']);

    $service = new CompetitorAd();
    $list = $service->get_competitor_ads_stat($order, $seq, $keyword);
    $total = count($list);
    $list = array_slice($list, $page * $page_size, $page_size);

    $this->output(array(
      'code' => 0,
      'msg' => 'ok',
      'list' => $list,
      'total' => $total,
    ));
  }
}