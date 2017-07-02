<?php
/**
 * Created by PhpStorm.
 * User: hemengyuan
 * Date: 15/7/28
 * Time: 下午4:46
 */

namespace diy\service;

use diy\utils\Utils;
use PHPExcel;
use PHPExcel_IOFactory;
use PHPExcel_Worksheet_Drawing;
use PHPExcel_Style_Alignment;
use PHPExcel_Writer_Excel5;

class Excel extends Base {
  public function export(array $params, $file_name) {
    $ea = new PHPExcel();
    $file = TEMP . PROJECT_NAME . "_$file_name.xlsx";
    $ews = $ea->getSheet(0);
    $ews->fromArray($params, ' ', 'A1');
    $writer = PHPExcel_IOFactory::createWriter($ea, 'Excel2007');
    $writer->save($file);

    header('Content-Type:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment; filename=$file_name.xlsx");
    header('Content-Length:'.filesize($file));
    readfile($file);
  }

  public function exportWithStyle(array $header, array $data, $fileName, array $styles = null) {
    $excel = new PHPExcel();

    // 设置文本对齐方式
    $excel->getDefaultStyle()->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
    $excel->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
    $objActSheet = $excel->getActiveSheet();

    $begin = 'A';
    $key = ord($begin);
    // 填充表格表头
    for($i = 0;$i < count($header);$i++) {
      $column = chr($key);
      $excel->getActiveSheet()->setCellValue($column . '1', "$header[$i]");
      // 设置表格宽度
      $objActSheet->getColumnDimension("$column")->setWidth($styles['width']);
      $key++;
    }

    $styles = Utils::array_pick($styles, array('height', 'width'));
    // 填充表格内容
    for ($i = 0; $i < count($data); $i++) {
      $span = ord($begin);
      $j = $i + 2;
      // 设置表格高度
      $excel->getActiveSheet()->getRowDimension($j)->setRowHeight($styles['height']);
      // 向每行单元格插入数据
      for ($row = 0; $row < count($data[$i]); $row++) {
        $column = chr($span);
        // 设置图片
        if (preg_match( '/\.(jpg|bmp|ico|jpeg|png)$/', $data[$i][$row])) {
          if (file_exists($data[$i][$row])) {
            // 实例化插入图片类
            $objDrawing = new PHPExcel_Worksheet_Drawing();
            // 设置图片路径
            $objDrawing->setPath($data[$i][$row]);
            // 设置图片高度
            $objDrawing->setHeight($styles['height']);
            // 设置图片要插入的单元格
            $objDrawing->setCoordinates($column . "$j");
            // 设置图片所在单元格的格式
            $objDrawing->setWorksheet($excel->getActiveSheet());
          } else {
            $excel->getActiveSheet()->setCellValue($column . "$j", '找不到该图片');
          }
        } else {
          // 设置普通文本
          $excel->getActiveSheet()->setCellValue($column . "$j", $data[$i][$row]);
        }
        $span++;
      }
    }

    $file = "/tmp/" . PROJECT_NAME . "_$fileName.xlsx";
    $write = new PHPExcel_Writer_Excel5($excel);
    $write->save($file);
    return $file;
  }
}