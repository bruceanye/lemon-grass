<?php
/**
 * Created by PhpStorm.
 * User: chensheng
 * Date: 15/7/16
 * Time: 上午10:57
 */

namespace diy\controller;

use diy\model\ADCutModel;
use diy\model\ADModel;
use diy\model\AgreementModel;
use diy\model\InvoiceModel;
use diy\service\AD;
use diy\service\Admin;
use diy\service\ADTransferStat;
use diy\service\Agreement;
use diy\service\Invoice;
use diy\service\Payment;
use diy\service\Quote;
use diy\service\QuoteStat;
use diy\service\Transfer;
use diy\utils\Utils;
use Exception;
use SQLHelper;

class InvoiceController extends BaseController {
  public function get_list() {
    $me = $_SESSION['id'];
    $pagesize   = isset($_REQUEST['pagesize']) ? (int)$_REQUEST['pagesize'] : 10;
    $page       = isset($_REQUEST['page']) ? (int)$_REQUEST['page'] : 0;
    $page_start = $pagesize * $page;
    $order = isset($_REQUEST['order']) ? trim($_REQUEST['order']) : 'id';
    $seq   = isset($_REQUEST['seq']) ? trim($_REQUEST['seq']) : 'DESC';
    $start = $_REQUEST['start'] ? $_REQUEST['start'] : date('Y-m-d');
    $end   = $_REQUEST['end'] ? $_REQUEST['end'] : date('Y-m-d');

    $filters = [
      'salesman' => [$me],
      'start' => $start,
      'end' => $end,
      'keyword' => $_REQUEST['keyword'],
    ];
    $status = $_REQUEST['status'];
    if ($status) {
      $sub_status = (int)$status;
      if (in_array($sub_status, InvoiceModel::$FAIL_STATUS)) { // 不通过（包括运营审核中）
        $filters = array_merge($filters, ['status' => $sub_status]);
      }

      if (in_array($sub_status, InvoiceModel::$IN_STATUS)) { // 审核中
        $filters = array_merge($filters, ['status' => [$sub_status, $sub_status - 8]]);
      }

      if (in_array($sub_status, InvoiceModel::$PASS_STATUS)) { // 通过
        $filters = array_merge($filters, ['pass_status' => $sub_status]);
      }
    }

    $filters         = array_filter($filters);
    $invoice_service = new Invoice();
    $result          = $invoice_service->get_invoice_info($filters, $page_start, $pagesize, array($order => $seq));
    $total           = $invoice_service->get_invoice_info_number($filters);

    $ad_transfer   = new ADTransferStat();
    $quote_service = new QuoteStat();
    foreach ($result as $inx => $invoice) {
      $invoice_id = $invoice['id'];
      list($invoice_ads, $ad_income, $ios_income) = $invoice_service->get_invoice_ad_by_invoiceid($invoice_id);
      $is_notice = false;
      foreach ($invoice_ads as $index => $ad) {
        $ad_id = $ad['ad_id'];
        $start = $ad['start'];
        $end   = $ad['end'];
        $daily_stat_list = $this->get_daily_stat($ad_transfer, $quote_service, $ad_id, $start, $end);

        if (count($daily_stat_list)) {
          $is_notice = true;
          break;
        }
      }
      $result[$inx] = array_merge($invoice, ['is_notice' => $is_notice]);
    }

    $this->output([
      'list' => $result,
      'msg' => 'ok',
      'total' => $total,
      'options' => ['types' => InvoiceModel::COMMENT_TYPES]
    ]);
  }

  public function get_init_params($applicant, $options) {
    $param = !is_string($_REQUEST['adids']) ? $_REQUEST['adids']['range'] : json_decode($_REQUEST['adids'], true)['range'];

    $ad_service         = new AD();
    $invoice_service    = new Invoice();
    $ad_transfer        = new ADTransferStat();
    $quote_stat_service = new QuoteStat();
    $transfer_service   = new Transfer();
    $quote_service      = new Quote();

    $ad_notice = array();
    $quote_res = array();
    $ad_id     = '';
    foreach ($param as $str) {
      $ad_id = $str['ad_ids'][0];
      $start = $str['start'];
      $end   = $str['end'];

      $transfer_ad_ids = $transfer_service->get_transfer_stat($start, $end);
      $quote_ads       = $quote_service->get_all_quote_ad($start, $end);
      $left_ad_ids     = array_unique(array_diff($transfer_ad_ids, $quote_ads));
      $res             = count($left_ad_ids) > 0 ? $ad_service->get_quote_by_ads($start, $end, $str['ad_ids'], $left_ad_ids) : $ad_service->get_quote_by_ads($start, $end, $str['ad_ids']);

      foreach ($res as $quote) {
        // 判断是否存在已经开过发票的广告
        $num = $invoice_service->is_invoice($quote['ad_id'], $quote['quote_start_date'], $quote['quote_end_date']);
        if ($num > 0) {$this->exit_with_error(50, '此广告已开过发票，点击确定返回发票页！', 403);}

        if (isset($quote['cpa_after'])) {unset($quote['cpa_after']);}
        if (isset($quote['quote_rmb_after'])) {unset($quote['quote_rmb_after']);}

        $quote       = array_merge($quote, ['start' => $start, 'end' => $end]);
        $quote_res[] = $quote;

        $id = $quote['ad_id'];
        // 余量提醒
        list($left_transfer_list, $new_end_time) = $this->get_cpa_stat($ad_service, $ad_transfer, $id, $start, $end);

        // 缺少渠道cpa的提醒
        $daily_stat_list = $this->get_daily_stat($ad_transfer, $quote_stat_service, $id, $start, $new_end_time);

        if (count($daily_stat_list) > 0 || count($left_transfer_list) > 0) {
          $ad_notice[] = [
            'ad_name' => $quote['ad_name'],
            'cid' => $quote['cid'],
            'notice' => $daily_stat_list,
            'left_transfer_notice' => $left_transfer_list
          ];
        }
      }
    }

    // 取合同信息
    $me = $_SESSION['id'];
    $adInfo = $ad_service->get_ad_info_by_id($ad_id);

    // 助理则读取第一个负责人的合同信息
    $agreement_service = new Agreement();
    $agreements        = $agreement_service->get_agreements(['id' => $adInfo['agreement_id']]);
    $agreement         = array_values($agreements)[0];
    $agreement['over'] = $agreement['end'] && $agreement['end'] < date('Y-m-d');
    $agreement['id']   = array_keys($agreements)[0];

    // 付款方信息
    $payInfo = $invoice_service->searchInvoiceInfo(['header' => $agreement['company'], 'applicant' => $me]);

    // 开票的时间段
    $quote_res   = Utils::array_sort($quote_res, 'quote_start_date');
    $start       = substr(current($quote_res)['quote_start_date'], 0, 7);
    $end         = substr(end($quote_res)['quote_start_date'], 0, 7);

    $sub_status  = $applicant['location'] == 'VIP' ? [0, 1] : ($applicant['location'] == 'Android' ? 2 : 3);
    $init = array_merge([
        'agreement_info' => $agreement,
        'agreement_number' => $agreement['agreement_id'],
        'company' => $agreement['company'],
        'apply_time' => date('Y-m-d'),
        'start' => $start,
        'end' => $end,
        'applicant' => $applicant['NAME'],
        'sub_status' => $sub_status,
        'ad_notice' => $ad_notice,
        'products' => $quote_res,
    ], $payInfo);

    $this->output(array(
      'code' => 0,
      'msg' => 'init',
      'invoice' => $init,
      'options' => array_merge(array(
        'init' => true,
      ), $options),
    ));
  }

  public function init($id) {
    $me                = $_SESSION['id'];
    $invoice_service   = new Invoice();
    $admin_service     = new Admin();
    $agreement_service = new Agreement();

    // 获取收款方业务负责人
    $chargers  = $admin_service->get_chargers($me);
    $applicant = $admin_service->get_sales_info($me);

    // 判断是否为助理
    $is_assistant = false;
    if ($chargers) {$is_assistant = true;}

    $options = [
      'chargers' => $chargers,
      'types' => InvoiceModel::$TYPE,
      'content_types' => InvoiceModel::$CONTENT_TYPE,
      'is_assistant' => $is_assistant,
      'cut_types' => ADCutModel::$CUT_TYPES,
      'sub_statuses' => InvoiceModel::$SUB_STATUS,
      'business_license_records' => AgreementModel::$BUSINESS_LICENSE_RECORD,
      'kinds' => InvoiceModel::$KINDS,
      'ad_app_types' => [ADModel::ANDROID => 'Android',ADModel::IOS => 'IOS']
    ];


    // 初始化创建发票信息
    if ($id === 'init') {$this->get_init_params($applicant, $options);}

    // 获取合同
    $res       = $invoice_service->get_invoice_by_id($id);
    $agreement = $agreement_service->get_agreement_info(array( 'id' => $res['agreement_id']));
    $agreement['over'] = $agreement['end'] && $agreement['end'] < date('Y-m-d');

    // 获取结算广告
    list($products, $ad_income, $ios_income) = $invoice_service->get_invoice_ad_by_invoiceid($id);

    // 草稿状态，重新获取结算广告数据
    $is_draft  = InvoiceModel::is_draft($res);
    if ($is_draft) {$products = $this->renew_data($id, $products);}

    $res = $invoice_service->get_invoice_by_id($id);
    $res = array_merge($res, [
      'agreement_info' => $agreement,
      'agreement_number' => $agreement['agreement_id'],
      'company' => $agreement['company'],
      'products' => $products,
      'start' => substr($res['start'], 0, 7),
      'end' => $res['end'] ? substr($res['end'], 0, 7) : substr($res['start'], 0, 7)
    ]);

    if (count($products) > 0) {
      $res = array_merge($res, ['income' => round($res['income'] / 100, 2),'income_first' => round($res['income_first'] / 100, 2)]);
    }

    if ($res['status'] == InvoiceModel::ALL_PASS) { $options = array_merge($options, ['view' => true]);}

    $this->output([
      'code' => 0,
      'msg' => 'fetched',
      'invoice' => $res,
      'options' => $options
    ]);
  }

  public function create() {
    $attr = $this->get_post_data();

    $invoice = new InvoiceModel($attr);
    try {
      $invoice->save($attr);
    } catch (Exception $e) {
      $this->exit_with_error($e->getCode(), $e->getMessage(), 400);
    }

    $this->output(array(
      'code' => 0,
      'msg' => 'ok',
      'invoice' => $invoice->attributes
    ));
  }

  public function update($id, $attr = null) {
    $attr = $attr ? $attr : $this->get_post_data();
    $attr = array_merge($attr, ['sale' => $_SESSION['id']]);
    $invoice = new InvoiceModel(array('id' => $id));

    $result = [];
    try {
      $result = $invoice->update($attr);
    } catch (Exception $e) {
      $this->exit_with_error($e->getCode(), $e->getMessage(), 400);
    }

    $res = array_merge(['code' => 0, 'msg' => '更新发票信息成功。'], $result);
    $this->output($res);
  }

  public function update_invoice_ad($id) {
    $attr = $this->get_post_data();
    $invoice = new InvoiceModel(array('id' => $id));

    try {
      $attr = $invoice->update_invoice_ad($attr);
    } catch (Exception $e) {
      $this->exit_with_error($e->getCode(), $e->getMessage(), 400);
    }

    $this->output(array(
      'code' => 0,
      'msg' => '修改广告信息成功',
      'invoice_ad' => $attr
    ));
  }

  public function updateReadComments($id) {
    $DB_write = $this->get_pdo_write();
    $DB = $this->get_pdo_read();

    $commentStatus = [12, 13];
    $appendSql = implode("','", $commentStatus);
    $sql = "SELECT COUNT('X') FROM `t_invoice_comment` WHERE `status` IN ('" . $appendSql ."') AND `invoice_id`=:invoice_id";
    $state = $DB->prepare($sql);
    $state->execute([':invoice_id' => $id]);
    $num = $state->fetchColumn();

    $status = $num ? 100 : 11;
    SQLHelper::update($DB_write, 't_invoice_comment', ['status' => $status], ['invoice_id' => $id]);

    $this->output([
      'code' => 0,
      'msg' => '附言已读',
      'data' => ['is_read' => true]
    ]);
  }

  public function delete($id) {
    $attr = array(
      'status' => 1
    );
    $this->update($id, $attr);
  }

  public function get_transfer_ad($ad_id) {
    $start = $_REQUEST['start'];
    $end = $_REQUEST['end'];

    $list = [];
    $noCheckList = [];
    $invoiceService = new Invoice();
    $adTransferService = new ADTransferStat();
    $quoteStatService = new QuoteStat();
    $adService = new AD();

    list($paymentChecks, $accountChecks) = $invoiceService->getAccountChecks($start, $end, Payment::CHECK);

    list($ids, $agreementID, $ads) = $this->getTransferQuoteADs($ad_id, $start, $end);
    foreach ($ids as $id) {
      // 剔除该段时间内已经开过票的广告
      $num = $invoiceService->is_invoice($id, $start, $end);
      if ($num > 0) continue;

      // 剔除没有关联合同的广告
      if (!isset($ads[$id]['agreement_id'])) continue;

      // 验证对账
      $noChecks = Utils::splitDates($start, $end, $accountChecks[$id]);
      $isCheck = count($noChecks) == 0 || in_array($id, $paymentChecks);

      $ad = [
        'ad_id' => $id,
        'ad_name' => $ads[$id]['ad_name'],
        'channel_id' => $ads[$id]['cid'],
        'channel' => $agreementID,
        'alias' => $ads[$id]['channel']
      ];
      if ($isCheck) {
        // 余量提醒
        list($leftTransferList, $newEndTime) = $this->get_cpa_stat($adService, $adTransferService, $id, $start, $end);

        // 缺少渠道cpa的提醒
        $cpaList = $this->get_daily_stat($adTransferService, $quoteStatService, $id, $start, $newEndTime);

        $list[] = array_merge($ad, [
          'left_transfer_notice' => $leftTransferList,
          'ad_notice' => $cpaList
        ]);
      } else {
        // 未对账提醒
        $noCheckList[] = array_merge($ad, ['dates' => $noChecks]);
      }
    }

    $this->output(array(
      'code' => 0,
      'msg' => "get",
      'list' => $list,
      'noCheckList' => $noCheckList
    ));
  }

  private function getTransferQuoteADs($ad_id, $start, $end) {
    $me = $_SESSION['id'];

    $adService = new AD();
    $channel = $adService->get_ad_channel_by_id($ad_id);

    $agreementService = new Agreement();
    $agreementID = $agreementService->get_agreement_info(array('company_short' => $channel))['id'];

    $filters = ['salesman' => $me, 'channel' => $channel];
    // 取出用户在该渠道下的所有广告
    $ads = $adService->get_all_ad_info($filters);

    // 取有登录cpa的广告
    $quoteService = new Quote();
    $quoteADs = $quoteService->get_all_quote_ad($start, $end);

    // 取有激活的广告
    $transferService = new Transfer();
    $transferADs = $transferService->get_transfer_stat($start, $end);

    $ids = array_unique(array_intersect(array_keys($ads), array_merge($quoteADs, $transferADs)));
    return array($ids, $agreementID, $ads);
  }

  public function get_daily_stat(ADTransferStat $ad_transfer, QuoteStat $quote_stat, $id, $start, $end) {
    $transfer = $ad_transfer->get_ad_transfer_stat_by_ad($id, $start, $end);
    $quote = $quote_stat->get_ad_quote($id, $start, $end);

    $stat_list = array();
    for ($stamp = strtotime($start); $stamp <= strtotime($end); $stamp += 86400) {
      $date = date('Y-m-d', $stamp);
      $stat = array(
        'date' => $date,
        'transfer' => (int)$transfer[$date]['transfer_total'],
        'cpa' => (int)$quote[$date]['nums']
      );
      if ($stat['transfer'] != 0 && $stat['cpa'] == 0) {
        $stat_list[] = $stat;
      }
    }
    return $stat_list;
  }

  public function get_cpa_stat(AD $ad_service, ADTransferStat $ad_transfer_service, $ad_id, $start, $end) {
    $filters = array(
      'status' => ADModel::OFFLINE,
      'off_start_time' => $start,
      'off_end_time' => $end
    );
    $latest_operation = $ad_service->get_ad_info_by_id($ad_id, $filters);
    $stat_list = array();
    if ($latest_operation) {
      $datetime = date('Y-m-d', strtotime($latest_operation['status_time']));
      $left_transfer = $ad_transfer_service->get_ad_transfer_stat_by_ad($ad_id, $datetime, $end);

      for ($stamp = strtotime($datetime) + 86400; $stamp <= strtotime($end); $stamp += 86400) {
        $date = date('Y-m-d', $stamp);
        $stat = array(
          'date' => $date,
          'transfer' => (int)$left_transfer[$date]['transfer_total'],
        );
        if ($stat['transfer'] != 0) {
          $stat_list[] = $stat;
        }
      }
    }

    // 判断是否下线
    if ($datetime) {
      return array($stat_list, $datetime);
    } else {
      return array($stat_list, $end);
    }
  }

  public function renew_data($id, $products) {
    $new_products = [];
    foreach ($products as $index => $ad) {
      $start = $ad['start'];
      $end   = $ad['end'];
      $ad_id = $ad['ad_id'];

      // $start~$end => array
      $key = $start . '~' . $end;
      $new_products[$key][] = $ad_id;
    }

    // 删除原来的开票广告
    $DB_write = $this->get_pdo_write();
    SQLHelper::delete($DB_write, 't_invoice_ad', ['i_id' => $id]);

    $ad_service       = new AD();
    $transfer_service = new Transfer();
    $quote_service    = new Quote();
    $result = [];
    foreach ($new_products as $key => $ad_ids) {
      $dates  = explode("~", $key);
      $start  = $dates[0];
      $end    = $dates[1];
      $ad_ids = array_unique($ad_ids);
      $transfer_ad_ids = $transfer_service->get_transfer_stat($start, $end);
      $quote_ads       = $quote_service->get_all_quote_ad($start, $end);
      $left_ad_ids     = array_unique(array_diff($transfer_ad_ids, $quote_ads));
      $quotes          = count($left_ad_ids) > 0 ? $ad_service->get_quote_by_ads($start, $end, $ad_ids, $left_ad_ids) : $ad_service->get_quote_by_ads($start, $end, $ad_ids);
      $result = array_merge($result, $quotes);
    }

    $income = 0;
    $invoice_service = new Invoice();
    foreach ($result as $index => $ad) {
      $params = array_merge(Utils::array_pick($ad, ['ad_id', 'quote_start_date', 'quote_end_date', 'cpa', 'start', 'end']), [
        'i_id' => $id,
        'cpa_after' => $ad['cpa'],
        'quote_rmb' => $ad['quote_rmb'] * 100,
        'quote_rmb_after' => $ad['quote_rmb'] * 100
      ]);
      SQLHelper::insert($DB_write, InvoiceModel::$T_INVOICE_AD, $params);
      $income = $income + $ad['income'];
    }

    // 更新发票的收入
    $income = $income * 100;
    SQLHelper::update($DB_write, InvoiceModel::$T_INVOICE, ['income' => $income, 'income_first' => $income], ['id' => $id]);
    list($products, $ad_income, $ios_income) = $invoice_service->get_invoice_ad_by_invoiceid($id);
    return $products;
  }
}