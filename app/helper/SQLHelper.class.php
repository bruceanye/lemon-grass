<?php
use diy\utils\Utils;

/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 14/11/18
 * Time: 下午6:48
 */

class SQLHelper {
  static $info = '';
  static $lastInsertId;

  public static function get_parameters($array, $id = null) {
    $params = array();
    foreach ( $array as $key => $value ) {
      if (!is_array($value)) {
        $params[":$key"] = $value;
      }
    }
    if ($id) {
      if (is_array($id)) {
        $count = 0;
        foreach ( $id as $value ) {
          $params[":id$count"] = $value;
          $count++;
        }
      } else {
        $params[':id'] = $id;
      }
    }
    return $params;
  }

  public static function get_in_fields( array $array ) {
    return implode(',', array_fill(0, count($array), '?'));
  }

  public static function insert(PDO $DB, $table, $attr, $return_row_count = true) {
    $attr = array_filter($attr, function ($value) {
      return $value !== '' && $value !== null;
    });
    $sql = self::create_insert_sql($table, $attr);
    $params = self::get_parameters($attr);
    $state = $DB->prepare($sql);
    $result = $state->execute($params);
    self::$info = $state->errorInfo();
    self::$lastInsertId = $DB->lastInsertId();
    return $result && $return_row_count ? $state->rowCount() : $result;
  }

  public static function insert_multi( PDO $DB, $table, $items, $return_row_count = true) {
    $sql = self::create_insert_multi_sql($table, array_keys($items[0]), count($items));
    $params = Utils::array_flatten($items);
    $state = $DB->prepare($sql);
    $result = $state->execute($params);
    self::$info = $state->errorInfo();
    return $result && $return_row_count ? $state->rowCount() : $result;
  }

  public static function update( PDO $DB, $table, $attr, $param, $return_row_count = true ) {
    if (!is_array($param)) {
      $param = array('id' => $param);
    }
    $sql = self::create_update_sql($table, $attr, $param);
    $params = $param;
    foreach ($attr as $key => $value) {
      if (isset($param[$key])) {
        $params[$key . '0'] = $value;
      } else {
        $params[$key] = $value;
      }
    }
    $params = self::get_parameters($params);
    $state = $DB->prepare($sql);
    $result = $state->execute($params);
    self::$info = $state->errorInfo();
    return $result && $return_row_count ? $state->rowCount() : $result;
  }

  public static function insert_update( PDO $DB, $table, $attr, $param, $return_row_count = true ) {
    $sql = self::create_insert_update_sql($table, $attr, $param);
    $params = self::get_parameters(array_merge($attr, $param));
    $state = $DB->prepare($sql);
    $result = $state->execute($params);
    self::$info = $state->errorInfo();
    return $result && $return_row_count ? $state->rowCount() : $result;
  }

  public static function insert_update_multi( PDO $DB, $table, $attr, $return_row_count = true ) {
    $sql = self::create_insert_update_multi_sql($table, array_keys($attr[0]), count($attr));
    $params = Utils::array_flatten($attr);
    $state = $DB->prepare($sql);
    $result = $state->execute($params);
    self::$info = $state->errorInfo();
    return $result && $return_row_count ? $state->rowCount() : $result;
  }

  public static function delete( PDO $DB, $table, $attributes, $returnRowCount = true ) {
    $sql = self::createDeleteSql($table, $attributes);
    $params = self::get_parameters($attributes);
    $state = $DB->prepare($sql);
    $result = $state->execute($params);
    self::$info = $state->errorInfo();
    return $result && $returnRowCount ? $state->rowCount() : $result;
  }

  public static function get_attr( PDO $DB, $table, $id ) {
    $keys = array_slice(func_get_args(), 3);
    $is_single = count($keys) === 1;
    $keys = implode('`, `', $keys);
    $sql = "SELECT `$keys`
            FROM `$table`
            WHERE `id`=:id";
    $state = $DB->prepare($sql);
    $state->execute(array(':id' => $id));
    return $state->fetch($is_single ? PDO::FETCH_COLUMN : PDO::FETCH_ASSOC);
  }

  private static function create_insert_multi_sql( $table, $keys, $lines ) {
    $values = implode(', ', array_fill(0, count($keys), '?'));
    $values = implode(', ', array_fill(0, $lines, "($values)"));
    $keys = implode('`, `', $keys);
    $sql = "INSERT `$table`
            (`$keys`)
            VALUES $values";
    return $sql;
  }

  public static function create_insert_sql($table, $array, $use_prepare = true) {
    $keys = array_keys($array);
    $values = array_values($array);
    $key = implode('`, `', $keys);
    if ($use_prepare) {
      $value = implode(', :', $keys);
      $sql = "INSERT INTO `$table`
              (`$key`)
              VALUES (:$value)";
    } else {
      $value = implode('\', \'', $values);
      $sql = "INSERT INTO `$table`
            (`$key`)
            VALUES ('$value')";
    }
    return $sql;
  }

  public static function parse_filter( array $filters = null, $is_append = false) {
    if (!$filters) {
      return '';
    }
    $sql = '';
    if (is_array($filters)) {
      foreach ($filters as $key => $filter) {
        if (isset($filter)) {
          $point = strpos($key, '.');
          $key_quoted = $point !== false ? substr($key, 0, $point + 1) . '`' . substr($key, $point + 1) . '`' : "`$key`";
          if (is_array($filter)) {
            $filter = implode("','", $filter);
            $sql .= " AND $key_quoted IN ('$filter')";
          } else {
            $sql .= " AND $key_quoted=:$key";
          }
        }
      }
    }
    return $is_append ? $sql : substr($sql, 5);
  }

  private static function create_update_sql( $table, $attr, $param ) {
    $fields = array();
    foreach ( $attr as $key => $value ) {
      $fields[] = "`$key`=:$key" . (isset($param[$key]) ? '0' : '');
    }
    $fields = implode(', ', $fields);
    $param_sql = self::parse_filter($param);
    $sql = "UPDATE `$table`
            SET $fields
            WHERE $param_sql";
    return $sql;
  }

  private static function create_insert_update_sql ($table, $attr, $param) {
    $keys = array_unique(array_merge(array_keys($attr), array_keys($param)));
    $values = implode(', :', $keys);
    $keys = implode('`, `', $keys);
    $fields = array();
    foreach ( $attr as $key => $value ) {
      $fields[] = "`$key`=:$key";
    }
    $fields = implode(', ', $fields);
    $sql = "INSERT INTO `$table`
            (`$keys`)
            VALUES (:$values) ON DUPLICATE KEY UPDATE $fields";
    return $sql;
  }

  private static function create_insert_update_multi_sql ($table, $keys, $lines) {
    $values = implode(', ', array_fill(0, count($keys), '?'));
    $values = implode(', ', array_fill(0, $lines, "($values)"));

    $fields = array();
    foreach ($keys as $key) {
      $fields[] = "`$key`=values(`$key`)";
    }
    $keys = implode('`, `', $keys);
    $fields = implode(', ', $fields);
    $sql = "INSERT `$table`
            (`$keys`)
            VALUES $values ON DUPLICATE KEY UPDATE $fields";
    return $sql;
  }

  private static function createDeleteSql( $table, $attributes ) {
    $attributes = array_map(function ($key) {
      return "`$key`=:$key";
    }, array_keys($attributes));
    $attributes = implode(' AND ', $attributes);
    $sql = "DELETE FROM `$table`
            WHERE $attributes";
    return $sql;
  }
}
