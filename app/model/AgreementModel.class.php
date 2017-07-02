<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 16/4/8
 * Time: 下午5:00
 */

namespace diy\model;


use diy\service\Agreement;
use PDO;

/**
 * @property string company
 * @property string company_short
 * @property string agreement_id
 */
class AgreementModel extends Base {
  static $COMPANY_TYPE = ['无', 'CP', '网盟', '换量', '个人', '开发者-公司', '外放渠道', '其他', '开发者-个人'];
  static $ITEM_TYPE = ['无', '安卓', 'IOS', '安卓及IOS'];
  static $BUSINESS_LICENSE_RECORD = ['无', '是', '否'];
  static $COMPANY_DIANJOY = ['北京无限点乐科技有限公司', '北京无限点乐科技有限公司第一分公司', '无限点乐（北京）信息科技发展有限公司'];
  static $COMPANY_DIANJOY_SHORT = ['点总', '点分', 'WFOE'];
  static $AGREEMENT_TEMPLATE = ['无', '点乐-自动延期', '点乐-非自动延期', '客户-自动延期', '客户-非自动延期', '补充协议'];
  static $FIELDS = ['company', 'ad_name', 'owner', 'sign_date', 'start', 'end', 'agreement_id', 'cycle', 'rmb',
    'comment', 'company_dianjoy', 'doc_date', 'agreement_template', 'archive', 'company_short', 'company_type',
    'item_type', 'user_email', 'business_license_record', 'channel_id'];
  static $SELECT = ['company_type', 'item_type', 'business_license_record', 'company_dianjoy', 'agreement_template'];
  const NO_PROTECTED_COMPANY_TYPES = array(5, 6, 8);
  const COMPANY_TYPES = array('BU', 'MJ', 'APP', 'OP', 'WF', 'SC', 'CG');
  
  public function fetch() {
    $service = new Agreement();
    $attr = $service->get_agreements([ 'id' => $this->id], PDO::FETCH_ASSOC)[0];
    $this->attributes = array_merge($this->attributes, $attr);
  }
}