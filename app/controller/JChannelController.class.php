<?php
/**
 * Created by PhpStorm.
 * User: chensheng
 * Date: 2017/9/10
 * Time: 上午11:16
 */

namespace diy\controller;

use diy\model\JChannelModel;
use diy\service\JChannel;
use diy\utils\Utils;
use Exception;
use SQLHelper;


class JChannelController extends BaseController {
    public function get_list() {
        $pagesize = isset($_REQUEST['pagesize']) ? (int)$_REQUEST['pagesize'] : 20;
        $page = isset($_REQUEST['page']) ? $_REQUEST['page'] : 0;
        $filters = Utils::array_pick($_REQUEST, ['keyword']);

        $channel = new JChannel();
        $result = $channel->get_channels($filters, $page, $pagesize);
        $total = $channel->get_total();

        $this->output(array(
            'code' => 0,
            'msg' => 'get',
            'list' => $result,
            'total' => $total,
            'options' => array(
                'types' => [1 => '中国', 2 => '外国'],
            )
        ));
    }

    public function update($id, $attr = null) {
        $attr = $attr ? $attr : $this->get_post_data();

        $client = new JChannelModel(['id' => $id]);
        try {
            $client->update_channel($attr);
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

        $client = new JChannelModel(['id' => $id]);
        try {
            $client->update_channel($attr);
        } catch (Exception $e) {
            $this->exit_with_error($e->getCode(), $e->getMessage(), 400, SQLHelper::$info);
        }

        $this->output(array(
            'code' => 0,
            'msg' => '删除成功'
        ));
    }

    public function create() {
        $attr = $this->get_post_data();

        $channel = new JChannelModel($attr);
        try {
            $channel->save();
        } catch ( Exception $e) {
            $this->exit_with_error($e->getCode(), $e->getMessage(), 400);
        }

        $this->output(array(
            'code' => 0,
            'msg' => '创建成功',
            'client' => $channel->attributes,
        ));
    }
}