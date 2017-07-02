<?php
/**
 * Created by PhpStorm.
 * Date: 2014/11/23
 * Time: 23:19
 * @overview 
 * @author Meatill <lujia.zhai@dianjoy.com>
 * @since 
 */

namespace diy\service;


use diy\utils\Utils;
use Memcache;
use PDO;
use Redis;

class Base {

  /** @var PDO 读库连接 */
  protected $DB;
  /** @var PDO 写库连接 */
  protected $DB_write;
  /** @var PDO stat 库连接 */
  protected $DB_stat;
  /** @var Redis redis 连接  */
  protected $redis;
  protected $memcache;

  protected $order;

  /**
   * 为确保事务能正常发挥作用，增加强制指定连接实例的方法
   *
   * @param PDO $DB_write
   */
  public function setDBWrite( PDO $DB_write ) {
    $this->DB_write = $DB_write;
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

  /**
   * @return PDO
   */
  protected function get_stat_pdo() {
    $this->DB_stat = $this->DB_stat ? $this->DB_stat : require PROJECT_DIR . '/app/connector/pdo_stat_read_remote.php';
    return $this->DB_stat;
  }

  /**
   * @return Redis
   */
  protected function get_redis() {
    $this->redis = $this->redis ? $this->redis : require PROJECT_DIR . '/app/connector/redis.php';
    return $this->redis;
  }

  /**
   * @return Memcache
   */
  protected function get_memcache() {
    $this->memcache = $this->memcache ? $this->memcache : require PROJECT_DIR . '/app/connector/memcache.php';
    return $this->memcache;
  }

  /**
   * 因为有多表连查,有时候需要将某个字段指向特定的表
   *
   * @param array|null $array 筛选条件
   * @param string $field 字段名
   * @param string $to 目标表
   * @param null $defaults 存入默认值
   *
   * @return mixed
   */
  protected function move_field_to($array, $field, $to, $defaults = null) {
    if (!$array && !$defaults) {
      return $array;
    }
    $array = is_array($array) ? $array : [];
    $to = $to . '.' . $field;
    if (array_key_exists($field, $array)) {
      $array[$to] = $array[$field];
      unset( $array[$field] );
    }
    if (!array_key_exists($to, $array) && $defaults) {
      $array[$to] = $defaults;
    }
    return $array;
  }

  /**
   * 根据传入的过滤数组取出过滤sql
   *
   * @param array $filters
   * @param array $options 各种参数
   *    @type boolean $to_string 是否输出为字符串
   *    @type boolean $is_append 是否为追加条件,`true` 的话将再前面追加 ` AND `
   *    @type array $spec 需要特殊处理的 `key`
   * @return array
   */
  protected function parse_filter( array $filters = null, array $options = [ ] ) {
    $options_default = array(
      'to_string' => true,
      'is_append' => false,
    );
    $options = array_merge($options_default, (array)$options);
    $conditions = $params = array();
    if ($options['spec']) {
      $spec = Utils::array_pick($filters, $options['spec']);
      if ($spec) {
        $filters = Utils::array_omit($filters, $options['spec']);
        list($conditions, $params) = $this->parseSpecialFilter($spec, $options);
      }
    }
    if (is_array($filters)) {
      foreach ($filters as $key => $filter) {
        if ($filter === null) {
          continue;
        }

        $point = strpos($key, '.');
        $key_name = $point !== false ? substr($key, $point + 1) : $key;
        $key = $point !== false ? substr($key, 0, $point + 1) . '`' . substr($key, $point + 1) . '`' : "`$key`";
        if (!is_array($filter)) {
          $conditions[]         = "$key=:$key_name";
          $params[":$key_name"] = $filter;
          continue;
        }

        if (isset($filter['operator'])) {
          list($condition, $param) = $this->parse_filter_by_operator($key, $filter, $key_name);
          $conditions = array_merge($conditions, $condition);
          $params = array_merge($params, $param);
        } else if (is_array($filter[0])) {
          $i = 0;
          foreach ($filter as $value) {
            $key_name_order = $key_name . $i;
            $i++;
            list($condition, $param) = $this->parse_filter_by_operator($key, $value, $key_name_order);
            $conditions = array_merge($conditions, $condition);
            $params = array_merge($params, $param);
          }
        } else {
          $filter = array('operator' => 'in', 'data' => $filter);
          list($condition, $param) = $this->parse_filter_by_operator($key, $filter, $key_name);
          $conditions = array_merge($conditions, $condition);
          $params = array_merge($params, $param);
        }
      }
    }
    return $this->outputFilter($conditions, $params, $options);
  }

  protected function parse_filter_by_operator($key, $filter, $key_name) {
    $conditions = $params = array();
    $filter['operator'] = strtoupper($filter['operator']);
    if (in_array($filter['operator'], array('LIKE', '>', '<', '<=', '>=', '!='))) {
      $conditions[] = "$key " . $filter['operator'] . " :$key_name";
      if ($filter['operator'] == 'LIKE') {
        $params[":$key_name"] = '%' . $filter['data'] . '%';
      } else {
        $params[":$key_name"] = $filter['data'];
      }
    } else if (in_array($filter['operator'], array('IS NULL', 'IS NOT NULL'))) {
      $conditions[] = "$key " . $filter['operator'];
    } else if (in_array($filter['operator'], array('IN', 'NOT IN'))) {
      $operator = $filter['operator'];
      $keys = [];
      $index = 0;
      foreach ( $filter['data'] as $value ) {
        $new_key = ":{$key_name}_{$index}";
        $params[$new_key] = $value;
        $keys[] = $new_key;
        $index++;
      }
      $filter = implode(',', $keys);
      $conditions[] = "$key $operator ($filter)";
    }

    return [$conditions, $params];
  }

  protected function outputFilter( $conditions, $params, $options ) {
    if ($options['to_string']) {
      $conditions = count($conditions) ? implode(' AND ', $conditions) : 1;
    }
    if (!is_array($conditions) && $conditions && $options['is_append']) {
      $conditions = ' AND ' . $conditions;
    }
    return [$conditions, $params];
  }

  /**
   * @param string|array $order
   *
   * @return string
   */
  protected function get_order( $order ) {
    $order     = $order === null ? $this->order : $order;
    if (is_string($order) && $order) {
      return "`$order` DESC";
    }

    if (is_array($order)) {
      $order = array_map(function ($key) use ($order) {
        return "`$key` " . strtoupper($order[$key]);
      }, array_keys($order));
      return implode(',', $order);
    }

    return '';
  }

  /**
   * @param array $extra_table
   *
   * @param int $count
   *
   * @return array
   */
  protected function parse_extra_tables( array $extra_table, $count = 98 ) {
    $tables = '';
    $extra_table = is_array($extra_table) ? $extra_table : [$extra_table];
    foreach ( $extra_table as $table ) {
      $alias = chr($count++);
      $tables .= " JOIN $table $alias ON a.`id`=$alias.`id`";
    }
    return $tables;
  }

  /**
   * 分析特定过滤条件
   * 由子类具体实现
   *
   * @param array $spec
   * @param array|null $options
   *
   * @return array
   */
  protected function parseSpecialFilter( $spec, $options = null ) {
    return [[], []];
  }
} 