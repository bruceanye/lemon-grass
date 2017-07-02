<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 15/4/9
 * Time: 下午4:39
 */

namespace diy\model;

use diy\utils\Utils;
use Exception;
use PDO;

class Base {
  protected $DB;
  protected $DB_write;
  protected $attributes = [];
  protected $idAttribute = 'id';

  public $error;
  public $id;
  public $changed = array();

  public function __construct(array $attr = null, array $options = null) {
    if ($options) {
      $this->idAttribute = isset($options['idAttribute']) ? $options['idAttribute'] : 'id';
    }
    $attr = $this->validate($attr);
    if ($attr) {
      $this->attributes = $attr;
    }
  }

  /**
   * 为确保事务能正常发挥作用，增加强制指定连接实例的方法
   * 
   * @param mixed $DB_write
   */
  public function setDBWrite( $DB_write ) {
    $this->DB_write = $DB_write;
  }
  public function __get($key) {
    if ($key == 'attributes') {
      return $this->attributes;
    }
    return $this->attributes[$key];
  }
  public function get($key) {
    return $this->attributes[$key];
  }
  public function set($key, $value) {
    if ($this->attributes[$key] !== $value) {
      $this->changed[$key] = $value;
      $this->attributes[$key] = $value;
    }
  }

  public function fetch() {
    if (!$this->id) {
      throw new Exception('缺少id，无法完成数据读取。', 10);
    }
  }
  public function remove() {
    
  }
  public function save(array $attr = null) {
    $attr = $this->validate( $attr );
    $this->attributes = array_merge($this->attributes, $attr);
  }
  public function update(array $attr = null) {
    $attr = $this->validate($attr);
    $this->changed = $attr;
    $this->attributes = array_merge($this->attributes, $attr);
  }
  public function omit( ) {
    $keys = func_get_args();
    return Utils::array_omit( $this->attributes, $keys );
  }
  public function pick() {
    $keys = func_get_args();
    return Utils::array_pick( $this->attributes, $keys );
  }
  public function toJSON() {
    return array_merge([], $this->attributes);
  }

  /**
   * @return PDO
   */
  protected function get_read_pdo() {
    $this->DB = $this->DB ? $this->DB : require PROJECT_DIR . '/app/connector/pdo_slave.php';
    return $this->DB;
  }

  /**
   * @return PDO
   */
  protected function get_write_pdo() {
    $this->DB_write = $this->DB_write ? $this->DB_write : require PROJECT_DIR . '/app/connector/pdo.php';
    return $this->DB_write;
  }

  protected function validate(array $attr = null) {
    if (!is_array( $attr )) {
      return [];
    }
    // 防XSS
    $attr = Utils::array_strip_tags($attr);

    if ($attr[$this->idAttribute]) {
      $this->id = $attr[$this->idAttribute];
    }
    return $attr;
  }
}