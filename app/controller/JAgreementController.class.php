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
use diy\utils\Utils;
use Exception;
use SQLHelper;


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
            'agreement' => $agreement->attributes,
        ));
    }

    public function update($id, $attr = null) {
        $attr = $attr ? $attr : $this->get_post_data();

        $agreement = new JagreementMOdel(['id' => $id]);
        try {
            $agreement->update_agreement($attr);
        } catch (Exception $e) {
            $this->exit_with_error($e->getCode(), $e->getMessage(), 400, SQLHelper::$info);
        }

        $this->output([
            'code' => 0,
            'msg' => '修改信息成功',
            'client' => $agreement->toJSON(),
        ]);
    }

    public function delete($id) {
        $attr = array('status' => 1);

        $agreement = new JAgreementModel(['id' => $id]);
        try {
            $agreement->update_agreement($attr);
        } catch (Exception $e) {
            $this->exit_with_error($e->getCode(), $e->getMessage(), 400, SQLHelper::$info);
        }

        $this->output(array(
            'code' => 0,
            'msg' => '删除成功'
        ));
    }

    public function get_list() {
        $pagesize = isset($_REQUEST['pagesize']) ? (int)$_REQUEST['pagesize'] : 20;
        $page = isset($_REQUEST['page']) ? $_REQUEST['page'] : 0;
        $filters = Utils::array_pick($_REQUEST, ['keyword']);

        $admin_service = new Admin();
        $sales = $admin_service->get_sales();

        $agreement = new JAgreement();
        $result = $agreement->get_agreements($filters, $page, $pagesize);
        $total = $agreement->get_total();

        $this->output(array(
            'code' => 0,
            'msg' => 'get',
            'list' => $result,
            'sales' => $sales,
            'total' => $total,
            'options' => array(
                'types' => [1 => '甲方', 2 => '乙方'],
                'sales' => $sales
            )
        ));
    }
}