<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 16/7/13
 * Time: 下午4:57
 */

namespace diy\controller;

use diy\model\RechargeHistoryCollection;
use diy\service\Auth;
use diy\service\User;
use diy\utils\Utils;

class UserController extends BaseController {
  public function getMyFinance() {
    $page = (int)$_REQUEST['page'];
    $pagesize = empty($_REQUEST['pagesize']) ? 50 : (int)$_REQUEST['pagesize'];
    $collection = new RechargeHistoryCollection([
      'user_id' => $_SESSION['id'],
    ]);
    $collection->fetch($page, $pagesize);

    $this->output([
      'code' => 0,
      'msg' => 'fetched',
      'list' => $collection->toJSON(),
    ]);
  }
  
  public function update() {
    $data = $this->get_post_data();

    // 有可能通过修改密码popup修改密码
    if ($_REQUEST['newpassword']) {
      $data = Utils::array_pick($_REQUEST, 'oldpassword', 'newpassword', 'repassword');
    }

    // 校验密码
    if ($data['newpassword']) {
      if ($data['newpassword'] != $data['repassword']) {
        $this->exit_with_error(11, '两次输入的密码不一致，请重新输入。', 403);
      }
      if (!preg_match('/[0-9a-zA-Z$!^#_@%&*.]{6,16}/', $data['newpassword'])) {
        $this->exit_with_error(12, '新的密码不合规则，请重新输入。', 403);
      }
      $auth = new Auth();
      if (!$auth->validate($_SESSION['user'], $data['oldpassword'], true)) {
        $this->exit_with_error(13, '旧密码不正确，请重新输入', 403);
      }
      $data['password'] = $auth->encrypt($_SESSION['user'], $data['newpassword']);
      $data = Utils::array_omit($data, 'oldpassword', 'newpassword', 'repassword');
    }

    $service = new User();
    $newData = null;
    if ($data['fullname']) {
        $newData['NAME'] = $data['fullname'];
    }
    if ($newData) {
        $check = $service->update_me($newData);
    } else {
        $check = $service->update_me($data);
    }
    if ($check) {
      $this->output([
        'code' => 0,
        'msg' => '修改成功',
        'me' => $data,
      ]);
    } else {
      $this->exit_with_error(400, '修改失败', 1);
    }
  }
}