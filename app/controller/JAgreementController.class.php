<?php
/**
 * Created by PhpStorm.
 * User: chensheng
 * Date: 2017/9/16
 * Time: 下午4:47
 */

namespace diy\controller;

use diy\model\JAgreementModel;
use diy\service\Admin;
use diy\service\JAgreement;
use Exception;


class JAgreementController extends BaseController {
    public function create() {
        $attr = $this->get_post_data();


        $agreement = new JAgreementModel($attr);
        try {
            $agreement->save();
        } catch ( Exception $e) {
            $this->exit_with_error($e->getCode(), $e->getMessage(), 400);
        }

        $this->output(array(
            'code' => 0,
            'msg' => '创建成功',
            'client' => $agreement->attributes,
        ));
    }

    public function get_list () {
        $pagesize = isset($_REQUEST['pagesize']) ? (int)$_REQUEST['pagesize'] : 20;
        $page = isset($_REQUEST['page']) ? $_REQUEST['page'] : 0;

        $admin_service = new Admin();
        $sales = $admin_service->get_sales();

        $agreement = new JAgreement();
        $result = $agreement->get_agreements($page, $pagesize);

        $this->output(array(
            'code' => 0,
            'msg' => 'get',
            'list' => $result,
            'sales' => $sales,
            'total' => 10,
            'options' => array(
            )
        ));
    }
}