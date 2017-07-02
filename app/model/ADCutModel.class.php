<?php
/**
 * Created by PhpStorm.
 * User: chensheng
 * Date: 15/11/13
 * Time: 下午6:06
 */

namespace diy\model;

class ADCutModel extends Base {
  const STATUS_NORMAL = 1;
  const STATUS_DEL = 0;
  const STATUS_DRAFT = -1;

  static $CUT_TYPE_NONE = 0;
  static $CUT_TYPE_CUSTOMER = 3;

  static $CUT_TYPES = array(1=>'跑超', 2=>'质量问题', 3=>'客户问题');
  static $OUR_PROBLEMS = array(1,2);
  static $HANDLE_TYPES = array(1 => '公司承担', 2 => '商务承担金额', 3 => '商务承担CPA');

  static $REPLY_TYPES = array('未核查', '有异议', '确认');
  static $OP_CUT_RESULTS = array('没有核减', '客户问题', '运营未复核', '运营审查');
}