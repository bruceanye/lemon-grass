<?php
/**
 * Created by PhpStorm.
 * User: chensheng
 * Date: 15/7/16
 * Time: 下午2:16
 */

namespace diy\model;

use diy\service\AD;
use diy\service\ADCut;
use diy\service\Admin;
use diy\service\Agreement;
use diy\service\Invoice;
use diy\service\Mailer;
use diy\service\Notification;
use SQLHelper;
use Exception;
use diy\utils\Utils;

class InvoiceModel extends Base {
  const COMMENT_TYPES = ['其它', '有无结算风险'];
  const FINANCE_OPERATE = 38; // 财务审核中
  const ALL_PASS = 50;
  const READ = 1;
  const NO_READ = 0;

  static $OPER_INVOICE = [71,100,101,116,156,33,94];

  static $T_INVOICE = 't_invoice';
  static $T_INVOICE_AD = 't_invoice_ad';
  static $T_AD_CUT = 't_ad_cut';

  static $DEL_STATUS = 1;

  static $AD_CUT = 1; // 核减结算
  static $AD_NORMAL = 0; // 正常结算

  static $TYPE = ['增值税普通发票', '增值税专用发票']; // 开票类型
  static $CONTENT_TYPE = ['信息服务费', '信息技术服务费', '技术服务费', '广告服务费（慎用）']; // 开票内容

  static $CUT_COMMENT = "发票系统自动核减金额";
  static $CUT_NORMAL = 1;

  static $ISOLATE_FIELDS = ['id', 'invoice_id', 'receiver', 'charger', 'handle_time', 'handle_user', 'applicant'];
  static $INVOICE_AD_FIELDS = ['products','header','type','content_type', 'pay_charger','pay_telephone','pay_address','start','end',
    'apply_time','charger','accept_telephone','accept_address','agreement_number', 'attachment','comment','income', 'attachment_desc',
    'income_first', 'sub_status', 'reason', 'kind', 'ios_income', 'ad_income'];
  static $INVOICE_INCOMES = ['income','income_first','joy_income','ad_income','ios_income'];
  static $FIELDS_UPLOAD = ['attachment'];

  static $STATUS = ['通过','未通过','审核中'];
  static $FAIL_STATUS = [8,9,19,29,39,49];
  static $IN_STATUS = [18,28,38,48];
  static $PASS_STATUS = [10,20,30,40,50];
  static $SUB_STATUS = array ( // 业务线分类
    0 => '战略合作部',
    1 => '代理业务部',
    2 => 'Android业务部',
    3 => 'IOS业务部'
  );
  static $KINDS = array(
    0 => 'Android',
    1 => 'IOS',
    2 => array('Android', 'IOS')
  );

  static $MANAGER_PIFU_STATUS = [8,9,10];
  static $OPERATION_STATUS = [18,19,20];
  static $SPECIAL_STATUS = [28,29,30];
  static $FINANCE_STATUS = [38,39,40];
  static $MANAGER_STATUS = [48,49,50];


  public function save(array $attr = null) {
    $attr = array_merge(Utils::array_pick($attr, self::$INVOICE_AD_FIELDS), array('applicant' => $_SESSION['id'], 'apply_time' => date('Y-m-d')));
    $attr = $this->validate($attr);

    $invoice_service = new Invoice();
    // 判断是否有已经开过票的广告
    if (isset($attr['products'])) {
      $ads = $attr['products'];
      foreach ($ads as $ad) {
        $num = $invoice_service->is_invoice($ad['ad_id'], $ad['quote_start_date'], $ad['quote_end_date']);
        if ($num > 0) {
          throw new Exception('错误操作,禁止给已经开过发票的广告再次申请', 51);
        }
      }
    }

    // 设置开票日期
    if ($attr['start']) {$attr['start'] = date($attr['start'] . '-01');}
    if ($attr['end']) {$attr['end'] = date($attr['end'] . '-01');}

    $DB_write = $this->get_write_pdo();
    // 取发票合同ID
    $agreement_service = new Agreement();
    $agreement = $agreement_service->get_agreement_info(array( 'agreement_id' => $attr['agreement_number']));
    $attr = array_merge(array('agreement_id' => $agreement['id'], 'status' => self::FINANCE_OPERATE), $attr);

    // 关联的广告信息
    $invoice_ads = $attr['products'];
    unset($attr['products']);
    unset($attr['agreement_number']);

    // 金额 X 100
    $incomes = $this->multi_100(Utils::array_pick($attr, self::$INVOICE_INCOMES));
    $attr = array_merge($attr, $incomes);

    // 插入发票表
    if (!SQLHelper::insert($DB_write, self::$T_INVOICE, $attr)) {
      throw new Exception('保存发票信息失败。', 2);
    }

    $invoice_id  = SQLHelper::$lastInsertId;
    $create_time = date('Y-m-d H:i:s');

    // 如果上传了对账单或者填写特批，则相应的发票审核人员发送新发票通知
    if ($attr['attachment'] || $attr['comment']) {
      $admin_service = new Admin();
      $manager = $admin_service->get_my_manager($_SESSION['id']);
      $notice_users = array_merge(self::$OPER_INVOICE, array($manager, $_SESSION['id']));

      $notice = new Notification();
      foreach ($notice_users as $item) {
        $notice->send(array(
          'uid' => $invoice_id,
          'admin_id' => $item,
          'user_id' => $_SESSION['id'],
          'alarm_type' => Notification::$NEW_INVOICE,
          'create_time' => $create_time
        ));
      }
    }

    $this->attributes = array_merge(array('id' => $invoice_id), $this->attributes);
    // 插入到发票广告关联表
    if (is_array($invoice_ads)) {
      $invoice_ad_arr = $this->construct_invoice_ads($invoice_ads, $invoice_id);
      if (!SQLHelper::insert_multi($DB_write, self::$T_INVOICE_AD, $invoice_ad_arr)) {
        throw new Exception('保存发票关联信息失败。', 3);
      }
    }
  }

  public function construct_invoice_ads($invoice_ads, $invoice_id) {
    $invoice_ad_arr = array();
    for ($i = 0, $len = count($invoice_ads); $i < $len; $i++) {
      $income = $invoice_ads[$i]['cpa'] * $invoice_ads[$i]['quote_rmb'];
      $income_after = $invoice_ads[$i]['cpa_after'] * $invoice_ads['quote_rmb_after'];
      $status = self::$AD_NORMAL; // 默认（不核减）
      if ($income != $income_after) {
        $status = self::$AD_CUT; // 核减
      }
      $invoice_ad_arr[$i] = array(
        'i_id' => $invoice_id,
        'ad_id' => $invoice_ads[$i]['ad_id'],
        'remark' => $invoice_ads[$i]['remark'],
        'cpa' => $invoice_ads[$i]['cpa'],
        'cpa_after' => $invoice_ads[$i]['cpa_after'],
        'quote_rmb' => $invoice_ads[$i]['quote_rmb'] * 100,
        'quote_rmb_after' => $invoice_ads[$i]['quote_rmb_after'] * 100,
        'quote_start_date' => $invoice_ads[$i]['quote_start_date'],
        'quote_end_date' => $invoice_ads[$i]['quote_end_date'],
        'start' => $invoice_ads[$i]['start'],
        'end' => $invoice_ads[$i]['end'],
        'status' => $status
      );
    }
    return $invoice_ad_arr;
  }

  public function update(array $attr = null) {
    $sale = $attr['sale'];
    unset($attr['sale']);

    $attr = $this->validate($attr);
    $DB_write = $this->get_write_pdo();

    $invoice_service = new Invoice();
    $old_invoice = $invoice_service->get_invoice_by_id($this->id);

    if (isset($attr['invoice_comment'])) {
      $params = [
        'invoice_id' => $this->id,
        'handler' => $sale,
        'create_time' => date('Y-m-d H:i:s'),
        'comment' => $attr['invoice_comment'],
        'to_status' => $old_invoice['status']
      ];
      SQLHelper::insert($DB_write, 't_invoice_comment', $params);

      // 初始化读取状态
      SQLHelper::update($DB_write, 't_invoice_comment', ['status' => 1], ['invoice_id' => $this->id]); 

      $comments = $invoice_service->get_invoice_comment($this->id);
      $attr = ['comments' => $comments];
    } else {
      $attr = array_merge($this->attributes, $attr);

      // 第一次上传对账单或者填写特批，发送新发票通知
      if (($attr['attachment'] || $attr['comment']) && !($old_invoice['attachment'] || $old_invoice['comment'])) {
        $admin_service = new Admin();
        $manager = $admin_service->get_my_manager($sale);
        $notice_users = array_merge(self::$OPER_INVOICE, array($manager, $sale));

        $notice = new Notification();
        foreach ($notice_users as $item) {
          $notice->send(array(
            'uid' => $this->id,
            'admin_id' => $item,
            'user_id' => $sale,
            'alarm_type' => Notification::$NEW_INVOICE,
            'create_time' => date('Y-m-d H:i:s')
          ));
        }
      }

      // 更新发票信息
      $check = SQLHelper::update($DB_write, self::$T_INVOICE, $attr, $this->id);
      if (!$check && $check != 0) {
        throw new Exception('更新发票信息失败。', 1);
      }

      // 修改业务线(sub_status)
      if ($attr['sub_status'] || $attr['sub_stats'] == 0) {
        $attr = array_merge($attr, array(
          'size' => in_array($attr['sub_status'], [0, 2]) ? 'Android' : 'iOS'
        ));
      }
    }

    return $attr;
  }

  public function update_invoice_ad(array $attr = null) {
    $attr = Utils::array_pick($attr, array('quote_rmb_after','cpa_after','remark'));
    $DB_write = $this->get_write_pdo();
    $attr = $this->judge_invoice_ad($attr);
    if (isset($attr['quote_rmb_after'])) { // 修改核减后单价
      $attr['quote_rmb_after'] = $attr['quote_rmb_after'] * 100;
    }

    // 更新发票关联表
    $this->attributes = array_merge($this->attributes, $attr);
    $result = SQLHelper::update($DB_write, self::$T_INVOICE_AD, $attr, $this->id);
    if (!$result && $result != 0) {
      throw new Exception('更新发票广告核减失败', 20);
    }

    $invoice_service = new Invoice();
    $invoice_ad = $invoice_service->get_invoice_ad_by_id($this->id);
    list($res, $ad_income, $ios_income) = $invoice_service->get_invoice_ad_by_invoiceid($invoice_ad['i_id']);
    $invoice = $invoice_service->get_invoice_by_id($invoice_ad['i_id']);

    // 验证状态
    $attr = $this->get_params_by_income($res);
    $attr = array_merge($attr, array (
      'ad_income' => $ad_income,
      'ios_income' => $ios_income
    ));
    // 更新发票表
    $result = SQLHelper::update($DB_write, self::$T_INVOICE, $attr, $invoice['id']);
    if (!$result && $result != 0) {
      throw new Exception('更新发票失败', 21);
    }
    return array_merge($this->attributes, $attr);
  }

  public function get_params_by_income($invoice_ads) {
    $income_total = 0;
    $income_first_total = 0;
    // 计算核减前后总收入
    foreach ($invoice_ads as $value) {
      $income_total += $value['income_after'];
      $income_first_total += $value['income'];
    }

    $incomes = $this->multi_100(['income' => $income_total, 'income_first' => $income_first_total]);
    $attr = array_merge($incomes, array(
      'status' => self::FINANCE_OPERATE
    ));
    return $attr;
  }

  public function judge_invoice_ad($attr) {
    $invoice_service = new Invoice();
    $invoice_ad = $invoice_service->get_invoice_ad_by_id($this->id);

    $status = self::$AD_NORMAL;
    $income = $invoice_ad['cpa'] * $invoice_ad['quote_rmb'];
    $income_after = 0;
    // 核减后cpa
    if (isset($attr['cpa_after'])) {
      $income_after = $attr['cpa_after'] * $invoice_ad['quote_rmb_after'];
    }

    // 核减后单价
    if (isset($attr['quote_rmb_after'])) {
      $income_after = $invoice_ad['cpa_after'] * $attr['quote_rmb_after'];
    }

    if ($income != $income_after) {
      $status = self::$AD_CUT;
    }

    $new_attr = array_merge($attr, array('status' => $status,));
    return $new_attr;
  }

  public static function is_draft($invoice) {
    return !($invoice['comment'] || $invoice['attachment'] || $invoice['income'] != $invoice['income_first']);
  }

  private function multi_100($attr) {
    foreach ($attr as $key => $value) {
      $attr[$key] = $value * 100;
    }
    return $attr;
  }

  public static function get_invoice_desc($invoice) {
    $status = $invoice['status'];
    $status_desc = "";
    switch ($status) {
      case '38':
        $status_desc = '财务审核中';
        break;
      case '48':
        $status_desc = '区域总监审核中';
        break;
      case '50':
        $status_desc = '审核已全部通过，等待开发票中';
        break;
      default:
        break;
    }

    if ($invoice['number']) {
      $status_desc = '等待发票邮寄中';
    }

    if ($invoice['express_number']) {
      $status_desc = '发票已邮寄';
    }
    return $status_desc;
  }

  protected function validate(array $attr = null) {
    $attr = parent::validate($attr);
    // 去掉上传中的绝对路径
    foreach (self::$FIELDS_UPLOAD as $field) {
      if ($attr[$field]) {
        $attr[$field] = str_replace(UPLOAD_URL, '', $attr[$field]);
      }
    }
    return $attr;
  }
}