<?php
/**
 * Created by PhpStorm.
 * User: chensheng
 * Date: 15/8/21
 * Time: 下午6:21
 */
namespace diy\service;

use Exception;

class TransferQueryBuilder extends Base {
  var $field=" SUM(rmb_total) AS rmb, SUM(transfer_total) AS transfer ";
  var $where=array();
  var $start="";
  var $end="";
  var $from="";
  var $groups=array();
  var $return;
  const RETURN_ASS=1;
  const RETURN_GROUP=2;
  var $COLUMN_DATE="transfer_date";

  public function group($group_by) {
    array_push($this->groups, strtolower($group_by));
    return $this;
  }

  public function where_date($start, $end = "") {
    $this->start = $start;
    $this->end = $end;
    return $this;
  }

  public function where($key, $value) {
    if (strpos(strtolower($key), $this->COLUMN_DATE) !== false) {
      throw new Exception($this->COLUMN_DATE . "不应该出现在where函数中，请使用where_date");
    }
    $this->where[$key] = $value;
    return $this;
  }

  public function output($output_mode=false) { // 输出为sql语句
    $sql = "SELECT ";
    foreach($this->groups as $k=>$v) { // group中的项会加入到输出的field中
      $sql =$sql." ". $v.",";
    }
    $sql = $sql." ".$this->field;

    $this->refresh_from();

    $sql =$sql. " FROM ".$this->from. " WHERE ";

    foreach($this->where as $k=>$v) {
      $sql=$sql. $this->get_where_sql_string($v,$k,$output_mode);
    }
    if($this->end == "") {
      $sql = $sql." ".$this->COLUMN_DATE."='".$this->start."' ";
    }
    else {
      $sql = $sql." ".$this->COLUMN_DATE.">='".$this->start."' AND ".$this->COLUMN_DATE."<='".$this->end."' ";
    }
    $sql=$sql." GROUP BY ";
    foreach($this->groups as $k=>$v) {
      $sql =$sql." ". $v.",";
    }
    $sql=trim($sql,",");
    return $sql;
  }

  public function get_where_sql_string($key_ids, $key_name,$mode=false) {
    if(trim($key_ids)=="") {
      $where_sql=$mode ? " " : " 1=0 and ";//如果mode为true，则对空key展示所有内容，反之则不展示任何内容
    }
    else if(strpos($key_ids,",")===false) {
      $where_sql=" $key_name = '$key_ids' and ";
    }
    else {
      $where_sql=" $key_name in('$key_ids') and ";
    }
    return $where_sql;
  }

  public function refresh_from() {
    if ($this->from == "") { //有可能直接指定，比如s_transfer_stat_app_ad，这样是无法自动判断的
      $this->from = " s_transfer_stat_app ";
      if ($this->if_column_in_where_or_group("transfer_h")) {
        $this->from = " s_transfer_stat_app_h ";
      }
      if ($this->if_column_in_where_or_group("ad_id")) {
        $this->from = " s_transfer_stat_ad ";
        if ($this->if_column_in_where_or_group("app_id")) { //即有app_id，又有ad_id
          $this->from = " s_transfer_stat_app_ad ";
        }
      }
    }
  }

  private function if_column_in_where_or_group($column) {
    if (array_key_exists($column, array_change_key_case($this->where))) {
      return true;
    }
    if (in_array($column, $this->groups)){ //此处因为group这个数组是以0,1,2这样的自然数为key{
      return true;
    }
    return false;
  }
}