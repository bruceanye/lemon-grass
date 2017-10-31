<?php
/**
 * Created by PhpStorm.
 * User: chensheng
 * Date: 2017/9/24
 * Time: 下午1:02
 */

namespace diy\controller;

use diy\service\Admin;
use diy\model\JADModel;
use diy\service\JAD;
use diy\utils\Utils;
use Exception;
use SQLHelper;


class JADController extends BaseController {
    public function create() {
        $attr = $this->get_post_data();

        $ad = new JADModel($attr);
        try {
            $ad->save();
        } catch ( Exception $e) {
            $this->exit_with_error($e->getCode(), $e->getMessage(), 400);
        }

        $this->output(array(
            'code' => 0,
            'msg' => '创建成功',
            'ad' => $ad->attributes,
        ));
    }

    public function update($id, $attr = null) {
        $attr = $attr ? $attr : $this->get_post_data();

        $ad = new JADModel(['id' => $id]);
        try {
            $ad->update_ad($attr);
        } catch (Exception $e) {
            $this->exit_with_error($e->getCode(), $e->getMessage(), 400, SQLHelper::$info);
        }

        $this->output([
            'code' => 0,
            'msg' => '修改信息成功',
            'ad' => $ad->toJSON(),
        ]);
    }

    public function delete($id) {
        $attr = array('status' => 1);

        $ad = new JADModel(['id' => $id]);
        try {
            $ad->update_ad($attr);
        } catch (Exception $e) {
            $this->exit_with_error($e->getCode(), $e->getMessage(), 400, SQLHelper::$info);
        }

        $this->output(array(
            'code' => 0,
            'msg' => '删除成功'
        ));
    }

    public function get_ad() {
        $this->output(array(
           'code' => 0,
            'msg' => 'success'
        ));
    }

    public function get_list() {
        $pagesize = isset($_REQUEST['pagesize']) ? (int)$_REQUEST['pagesize'] : 20;
        $page = isset($_REQUEST['page']) ? $_REQUEST['page'] : 0;
        $filters = Utils::array_pick($_REQUEST, ['keyword']);

        $admin_service = new Admin;
        $owners = $execute_owners = $admin_service->get_sales();

        $ad_service = new JAD();
        $result = $ad_service->get_ads($filters, $page, $pagesize);
        $total = $ad_service->get_total();

        $cooperation_types1 = [
            1 => '积分墙',
            2 => '机刷',
            3 => '评论',
            4 => '下载量',
            5 => 'CPC',
            6 => 'CPT',
            7 => 'CPD'
        ];
        $cooperation_types2 = [
            8 => '注册',
            9 => '成功放款',
            10 => '返佣',
            11 => '注册+返佣',
            12 => '授信'
        ];
        $this->output(array(
            'code' => 0,
            'msg' => 'get',
            'owners' => $owners,
            'execute_owners' => $execute_owners,
            'list' => $result,
            'total' => $total,
            'options' => array(
                'owners' => $owners,
                'execute_owners' => $execute_owners,
                'types' => [1 => '应用优化', 2 => '贷款平台'],
                'cooperation_types1' => $cooperation_types1,
                'cooperation_types2' => $cooperation_types2
            )
        ));
    }
}