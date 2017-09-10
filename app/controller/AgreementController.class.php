<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 15/5/19
 * Time: 上午11:00
 */

namespace diy\controller;


use diy\model\AgreementModel;
use diy\service\Admin;
use diy\service\Agreement;
use diy\service\Mailer;

class AgreementController extends BaseController {

  public function create() {
      $attr = $this->get_post_data();


  }

  public function get_list() {
    $pagesize = isset($_REQUEST['pagesize']) ? (int)$_REQUEST['pagesize'] : 100;
    $page = isset($_REQUEST['page']) ? $_REQUEST['page'] : 0;
    $page_start = $page * $pagesize;
    $filters = array(
      'keyword' => $_REQUEST['keyword']
    );
    if ($_REQUEST['today']) {
      $filters['today'] = $_REQUEST['today'];
    }

    $service = new Agreement();
    $admin = new Admin();
    $me = $_SESSION['id'];

    $relative = array();
    $relative_sales = $admin->get_sales_by_me($me);
    if ($relative_sales) {
      foreach ($relative_sales as $id => $value) {
        $relative[] = array(
          'key' => $id,
          'value' => $value,
        );
      }
    }

    $agreement_list = $service->get_my_agreement($filters, $page_start, $pagesize);
    $total = $service->get_my_agreement_total($filters);

    $agreement_list = array_map(function ($agreement) {
      foreach ( AgreementModel::$SELECT as $key ) {
        if (!is_numeric($agreement[$key])) {
          $key_name = strtoupper($key);
          $agreement[$key] = array_search($agreement[$key], AgreementModel::${$key_name});
        }
      }
      return $agreement;
    }, $agreement_list);

    $this->output(array(
      'code' => 0,
      'msg' => 'get',
      'list' => $agreement_list,
      'total' => $total,
      'options' => array(
        'relativeSales' => $relative_sales,
        'types' => AgreementModel::$COMPANY_TYPE,
        'company_dianjoys' => AgreementModel::$COMPANY_DIANJOY,
      )
    ));
  }

  public function renew( $id ) {
    $mailer = new Mailer();
    $service = new Agreement();
    $me = $_SESSION['id'];
    $attr = $this->get_post_data();
    $attr['fullname'] = $_SESSION['fullname'];

    $agreement = $service->get_agreement_info([ 'id' => $id]);
    if (!$agreement) {
      $this->exit_with_error(11, '参数错误', 400);
    }
    if ($agreement['owner'] != $me) {
      $this->exit_with_error(10, '你不能对别人的合同进行操作', 403);
    }

    $agreement['company_type'] = Agreement::$TYPE[$agreement['company_type']];
    $content = $mailer->create('agreement-renew', $agreement, $attr);
    $to = [
      'shuangyan.pan@dianjoy.com',
      'business@dianjoy.com',s
    ];
    $cc = [
      'costing@dianjoy.com',
      'op@dianjoy.com',
      $_SESSION['user'] . '@dianjoy.com',
    ];
    $check = $mailer->send($to, '商务申请延长合同执行保护期', $content, $cc);

    if ($check) {
      $this->output([
        'code' => 0,
        'msg' => '发送成功',
      ]);
    } else {
      $this->exit_with_error(20, '发送邮件失败', 400, $check);
    }
  }
}