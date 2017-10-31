<?php
/**
 * Created by PhpStorm.
 * User: chensheng
 * Date: 2017/10/28
 * Time: 下午10:00
 */
namespace diy\controller;

use diy\service\JADTransfer;
use diy\model\ADModel;
use Exception;


class JADTransferController extends BaseController {
    public function getList($key) {
        $jadTransfer = new JADTransfer();
        $start = $_REQUEST['start'];
        $end = $_REQUEST['end'];

        $list = $jadTransfer->getClickAdsByDate(array('ad_id' => $key), $start, $end);
        $this->output(array(
            'code' => 0,
            'msg' => 'ok',
            'list' => $list,
            'options' => array(
                'feedbacks' => ADModel::$FEEDBACK,
                'cycles' => ADModel::$CYCLE,
            ),
        ));
    }

    public function record($param) {
        $data = $this->get_post_data();

        $jadTransfer = new JADTransfer();
        try {
            $jadTransfer->record($data, $param);
        } catch (Exception $e) {
            $this->exit_with_error($e->getCode(), $e->getMessage(), 401);
        }

        $this->output(array(
            'code' => 0,
            'msg' => 'ok',
            'list' => $data
        ));
    }
}