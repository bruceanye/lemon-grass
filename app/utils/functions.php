<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 14/11/19
 * Time: 下午5:55
 */

/**
 * 按常见方式显示文件尺寸
 * @param int $size
 *
 * @return string
 */
function format_file_size ($size) {
  $units = array('B', 'KB', 'MB', 'GB');

  if ($size > 0) {
    $unit = intval(log($size, 1024));

    if (array_key_exists($unit, $units)) {
      return round($size / pow(1024, $unit), 2) . $units[$unit];
    }
  }

  return $size;
}