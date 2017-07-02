<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 16/5/10
 * Time: 下午2:35
 */

namespace diy\model;


use diy\service\Channel;

class ChannelCollection extends Collection {
  public function fetch( $page = 0, $pageSize = 0, $is_map = false ) {
    $service = new Channel();
    $this->items = $service->get_channel($this->filters, $page, $pageSize);
    if ($is_map) {
      $this->items = array_combine(array_column($this->items, $this->idAttribute), $this->items);
    }
    return $this->items;
  }

  public function length(  ) {
    $service = new Channel();
    return $service->get_channel_num($this->filters);
  }
}