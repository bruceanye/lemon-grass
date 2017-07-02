<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 14/12/23
 * Time: 下午3:40
 */

namespace diy\controller;

use diy\service\Notification;

class NoticeController extends BaseController {
  private function get_service() {
    return new Notification();
  }
  public function get_list() {
    $service = $this->get_service();
    $me = $_SESSION['id'];
    $role = (int)$_SESSION['role'];
    $latest = (int)$_GET['latest'];

    $alarms = $service->get_notice($me, $role, $latest);
    $this->output(array(
      'code' => 0,
      'msg' => 'fetched',
      'list' => $alarms,
    ));
  }

  public function delete($id) {
    $service = $this->get_service();
    $id = explode(',', $id);

    $check = $service->set_status(array('id' => $id), Notification::$HANDLED);
    if (!$check) {
      $this->exit_with_error(1, '操作失败', 400);
    }

    $this->output(array(
      'code' => 0,
      'msg' => '标记为已读。',
    ));
  }
}