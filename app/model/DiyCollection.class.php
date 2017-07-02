<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 16/7/14
 * Time: 下午3:53
 */

namespace diy\model;


use diy\service\Diy;

class DiyCollection extends Collection {
  public function fetch( $page = 0, $size = 0, $is_map = false  ) {
    $service = new Diy();
    $this->items = $service->getDiy($this->filters, $page, $size, $this->order);
    return $this->items;
  }

  public function size( array $filters = null ) {
    $service = new Diy();
    return (int)$service->count($filters);
  }
}