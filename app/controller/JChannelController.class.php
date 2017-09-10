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
use Exception;


class JChannelController extends BaseController {
    public function get_list() {
        $pagesize = isset($_REQUEST['pagesize']) ? (int)$_REQUEST['pagesize'] : 20;
        $page = isset($_REQUEST['page']) ? $_REQUEST['page'] : 0;

        $channel = new JChannel();
        $result = $channel->get_channels($page, $pagesize);

        $this->output(array(
            'code' => 0,
            'msg' => 'get',
            'list' => $result,
            'total' => 10,
            'options' => array(
                'channel_types' => [1 => '中国', 2 => '外国'],
            )
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