<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 16/7/13
 * Time: 下午5:26
 */

namespace diy\model;


use diy\service\User;

class RechargeHistoryCollection extends Collection {
  public function fetch( $page = 0, $size = 0, $is_map = false ) {
    $service = new User();
    $this->items = $service->getUserFinance($this->filters, $page * $size, $size);
    $this->items = array_map(function ($item) {
      $item['total'] = $item['balance'] + $item['award'];
      $item['invoice'] = (int)$item['invoice'];
      $item['rmb_ready'] = (int)$item['rmb_ready'];
      return $item;
    }, $this->items);
    return $this->items;
  }
}