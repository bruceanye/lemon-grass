<?php
/**
 * Created by PhpStorm.
 * User: chensheng
 * Date: 2017/9/9
 * Time: 下午3:46
 */

namespace diy\controller;

use diy\model\ClientModel;
use diy\service\Client;
use SQLHelper;
use Exception;


class ClientController extends BaseController {
    public function create() {
        $attr = $this->get_post_data();

        $client = new ClientModel($attr);
        try {
            $client->save();
        } catch ( Exception $e) {
            $this->exit_with_error($e->getCode(), $e->getMessage(), 400);
        }

        $this->output(array(
            'code' => 0,
            'msg' => '创建成功',
            'client' => $client->attributes,
        ));
    }

    public function update($id, $attr = null) {
        $attr = $attr ? $attr : $this->get_post_data();

        $client = new ClientModel(['id' => $id]);
        try {
            $client->update_client($attr);
        } catch (Exception $e) {
            $this->exit_with_error($e->getCode(), $e->getMessage(), 400, SQLHelper::$info);
        }

        $this->output([
            'code' => 0,
            'msg' => '修改信息成功',
            'client' => $client->toJSON(),
        ]);
    }

    public function delete($id) {
        $attr = array('status' => 1);

        $client = new ClientModel(['id' => $id]);
        try {
            $client->update_client($attr);
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

        $client = new Client();
        $result = $client->get_clients($page, $pagesize);

        $this->output(array(
            'code' => 0,
            'msg' => 'get',
            'list' => $result,
            'total' => 10,
            'options' => array(
                'types' => [1 => '中国', 2 => '外国'],
            )
        ));
    }
}