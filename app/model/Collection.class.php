<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 16/5/10
 * Time: 下午2:33
 */

namespace diy\model;


use diy\service\Base as Super;

class Collection extends Super {
  protected $filters;
  protected $order;
  protected $items;
  protected $idAttribute = 'id';

  public function __construct(array $filters = null, array $options = null) {
    $this->filters = $filters;
    $default_options = [];
    $options = array_merge($default_options, (array)$options);
    if ($options['autoFetch']) {
      $this->fetch(0, 0, $options['map']);
    }
  }

  public function fetch( $page = 0, $size = 0, $is_map = false) {
    $this->items = [];
    return $this->items;
  }

  public function get( $id ) {
    return $this->items[$id];
  }

  public function size() {
    return 0;
  }

  public function setOrder($order) {
    $this->order = $order;
  }

  public function toJSON(  ) {
    return $this->items;
  }

  public function update( array $attr ) {
    
  }
}