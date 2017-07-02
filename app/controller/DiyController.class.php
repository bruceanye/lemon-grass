<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 16/7/14
 * Time: 下午3:51
 */

namespace diy\controller;


use diy\model\ADModel;
use diy\model\DiyCollection;
use diy\model\DiyModel;
use diy\model\DiyUserModel;
use diy\service\DiyUser;
use diy\service\Mailer;
use diy\utils\Utils;
use Exception;
use SQLHelper;

class DiyController extends BaseController {
  public function create(  ) {
    $attr = $this->get_post_data();
    $model = new DiyModel();
    try {
      $model->save($attr);
    } catch ( Exception $e) {
      $this->exit_with_error($e->getCode(), $e->getMessage(), 400, SQLHelper::$info);
    }

    // 给运营发邮件
    $this->sendCreateDiyEmail($model);

    $this->output([
      'code' => 201,
      'msg' => '创建投放计划成功！',
      'data' => $model->toJSON(),
    ]);
  }

  public function delete( $id ) {
    $model = new DiyModel(['id' => $id]);
    if (!$model->checkOwner()) {
      $this->exit_with_error(10, '您不能操作次投放计划', 403);
    }

    $model->fetch();
    if (!$model->status == DiyModel::WAIT) {
      $this->exit_with_error(20, '此投放计划已经开始审核，不能删除', 403);
    }

    $check = false;
    try {
      $check = $model->update([
        'status' => 100,
      ]);
    } catch (Exception $e) {
      $this->exit_with_error($e->getCode(), $e->getMessage(), 400, SQLHelper::$info);
    }
    if ($check) {
      $this->output([
        'code' => 0,
        'msg' => '删除投放计划成功',
      ]);
    } else {
      $this->exit_with_error(20, '删除投放计划失败', 400);
    }
  }

  public function getList(  ) {
    $page = $_REQUEST['page'] ? (int)$_REQUEST['page'] : 0;
    $pageSize = $_REQUEST['pagesize'] ? (int)$_REQUEST['pagesize'] : 10;
    $order = $_REQUEST['order'] ? $_REQUEST['order'] : 'create_time';
    $order = [ $order => $_REQUEST['seq'] ? $_REQUEST['seq'] : 'DESC'];
    $filters = Utils::array_pick($_REQUEST, 'status');
    if (!array_key_exists('status', $filters)) {
      $filters['status'] = [
        'operator' => '<',
        'data' => DiyModel::DELETE,
      ];
    }
    $filters['owner'] = $_SESSION['id']; 
    $collection = new DiyCollection($filters);
    $collection->setOrder($order);
    $collection->fetch($page, $pageSize);

    $filters['status'] = [
      'operator' => '<',
      'data' => DiyModel::DELETE
    ];
    $total = $collection->size($filters);
    $filters['status'] = DiyModel::WAIT;
    $wait = $collection->size($filters);
    $filters['status'] = DiyModel::IN_REVIEW;
    $in_review = $collection->size($filters);
    $filters['status'] = DiyModel::SUCCESS;
    $success = $collection->size($filters);
    $filters['status'] = DiyModel::FAILED;
    $failed = $collection->size($filters);
    switch ($filters['status']) {
      case DiyModel::WAIT:
        $count = $wait;
        break;

      case DiyModel::IN_REVIEW:
        $count = $in_review;
        break;

      case DiyModel::SUCCESS:
        $count = $success;
        break;

      case DiyModel::FAILED:
        $count = $failed;
        break;

      default:
        $count = $total;
        break;
    }

    $this->output([
      'code' => 0,
      'msg' => 'fetched',
      'list' => $collection->toJSON(),
      'total' => $count,
      'numbers' => [
        'status' => (string)$filters['status'],
        'total' => $total,
        'wait' => $wait,
        'in_review' => $in_review,
        'success' => $success,
        'failed' => $failed,
      ],
    ]);
  }

  public function get( $id ) {
    $model = new DiyModel(['id' => $id]);
    $model->fetch();
    
    $this->output([
      'code' => 0,
      'msg' => 'fetched',
      'data' => $model->toJSON(),
    ]);
  }

  public function update($id) {
    $model = new DiyModel(['id' => $id]);
    if (!$model->checkOwner()) {
      $this->exit_with_error(100, '这不是您的投放计划，不能修改。', 403);
    }

    $model->fetch();
    if ($model->status != DiyModel::WAIT && $model->status != DiyModel::FAILED) {
      $status = $model->status == DiyModel::IN_REVIEW ? '正在审核中' : '已通过';
      $this->exit_with_error(110, '此投放计划' . $status . '，不能修改', 403);
    }

    $check = false;
    $attr = $this->get_post_data();
    $attr = Utils::array_omit($attr, 'status');
    try {
      $check = $model->update($attr);
    } catch (Exception $e) {
      $this->exit_with_error($e->getCode(), $e->getMessage(), 400);
    }
    if ($check) {
      $this->output([
        'code' => 0,
        'msg' => '修改投放计划成功',
        'data' => $attr,
      ]);
    } else {
      $this->exit_with_error(10, '修改投放计划失败', 400);
    }
  }

  private function sendCreateDiyEmail( DiyModel $model ) {
    $service = new DiyUser();
    $owner = $service->getOwner(['id' => $_SESSION['id']]);
    $mailer = new Mailer();
    $content = $mailer->create('diy-new', $model->toJSON(), [
      'quote_rmb' => $model->quote_rmb / 100,
      'put_ipad' => ['iPhone + iPad', 'iPad', 'iPhone'][$model->put_ipad],
      'is_api' => $model->is_api == 1 ? '是' : '否',
      'cate' => DiyUserModel::CATES[$model->cate],
      'settle_cycle' => DiyUserModel::SETTLE_CYCLES[$_SESSION['settle_cycle']],
      'settle_type' => DiyUserModel::SETTLE_TYPES[$model->settle_type],
    ]);
    $mailer->send([
      $_SESSION['email'],
    ], '[点乐 iOS 投放计划] ' . $model->ad_name . ' 请回复点乐运营', $content, [
      'qiong.xu@dianjoy.com',
      'op-ios@dianjoy.com',
      $owner . '@dianjoy.com',
    ]);
  }
}