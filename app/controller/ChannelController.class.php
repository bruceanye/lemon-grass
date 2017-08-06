<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 15/5/19
 * Time: 上午11:00
 */

namespace diy\controller;


use diy\model\ADCollection;
use diy\model\ADModel;
use diy\model\ChannelCollection;
use diy\model\ChannelModel;
use diy\model\DiyUserModel;
use diy\service\AD;
use diy\service\Channel;
use diy\utils\Utils;
use Exception;
use function MongoDB\BSON\toJSON;
use SQLHelper;

class ChannelController extends BaseController {
  public function getADs($id) {
    $page = isset($_REQUEST['page']) ? (int)$_REQUEST['page'] : 0;
    $pageSize = isset($_REQUEST['pagesize']) ? (int)$_REQUEST['pagesize'] : 10;

    $service = new AD();
    $result = $service->getADByChannel($id, $page, $pageSize);
    $total = $service->countADByChannel($id);

    $this->output(array(
      'code' => 0,
      'msg' => 'get',
      'list' => $result,
      'total' => $total,
      'options' => [
        'feedbacks' => ADModel::$FEEDBACK,
      ]
    ));
  }

  public function updateFeedback( $channel_id, $pack_name ) {
    $data = Utils::array_pick($this->get_post_data(), 'feedback');
    $collection = new ADCollection([
      'channel_id' => $channel_id,
      'pack_name' => $pack_name,
    ]);
    $check = $collection->update($data);
    
    if ($check) {
      $this->output([
        'code' => 0,
        'msg' => '修改成功',
      ]);
    } else {
      $this->exit_with_error(10, '修改失败', 403);
    }
  }

  public function create() {
    $attr = $this->get_post_data();
    if (!$attr['sales']) {
      $attr['sales'] = $_SESSION['id'];
    }
    $channel = new ChannelModel($attr);
    if ($channel->error) {
      $this->exit_with_error($channel->error);
    }

    try {
      $channel->save();
    } catch ( Exception $e) {
      $this->exit_with_error($e->getCode(), $e->getMessage(), 400);
    }

    $this->output(array(
      'code' => 201,
      'msg' => '创建成功',
      'channel' => $channel->attributes,
    ));
  }

  public function create_new() {
      $attr = $this->get_post_data();
      $channel = new ChannelModel($attr);

      try {
          $channel->save_new();
      } catch ( Exception $e) {
          $this->exit_with_error($e->getCode(), $e->getMessage(), 400);
      }

      $this->output(array(
          'code' => 201,
          'msg' => '创建成功',
          'channel' => $channel->attributes,
      ));
  }


  public function get_channel_info( $id ) {
    $channel = new ChannelModel(['id' => $id]);
    $channel->fetch();
    $this->output([
      'code' => 0,
      'msg' => 'fetched',
      'data' => $channel->attributes,
    ]);
  }

  public function get_list() {
    $pagesize = isset($_REQUEST['pagesize']) ? (int)$_REQUEST['pagesize'] : 20;
    $page = isset($_REQUEST['page']) ? $_REQUEST['page'] : 0;
    $filters = array(
      'owner' => $_SESSION['id'],
      'keyword' => $_REQUEST['keyword'],
    );

    $channel = new ChannelCollection($filters);

    $channel_list = $channel->fetch($page, $pagesize);
    $total = $channel->length();

    $this->output(array(
      'code' => 0,
      'msg' => 'get',
      'list' => $channel_list,
      'total' => $total,
      'options' => array(
        'channel_types' => ChannelModel::$TYPE,
      )
    ));
  }

  public function get_list_new() {
      $channel = new Channel();
      $list = $channel->get_base_channel();
      $this->output(array(
         'list' => $list
      ));
  }

  public function update_new($id, $attr = null) {
      $channel = new ChannelModel(['id' => $id]);
      $attr = $attr ? $attr : $this->get_post_data();
      try {
          $channel->update_channel($attr);
      } catch (Exception $e) {
          $this->exit_with_error($e->getCode(), $e->getMessage(), 400, SQLHelper::$info);
      }

      $this->output([
          'code' => 0,
          'msg' => '修改广告主信息成功',
          'channel' => $channel->toJSON(),
      ]);
  }

  public function get_channel_prepaid($id) {
    $pageSize = $_REQUEST['pagesize'] ? (int)$_REQUEST['pagesize'] : 20;
    $page = (int)$_REQUEST['page'];
    
    $channel = new ChannelModel(['id' => $id]);
    $prepaid = $channel->fetchPrepaid($page, $pageSize);
    $total = $channel->prepaidLength();
    
    $this->output([
      'code' => 0,
      'msg' => 'fetched',
      'list' => $prepaid,
      'total' => $total,
    ]);
  }

  public function update($id, $attr = null) {
    $attr = $attr ? $attr : $this->get_post_data();
    $channel = new ChannelModel(array('id' => $id));
    $channel->fetch();

    if ($channel->error) {
      $this->exit_with_error($channel->error);
    }
    
    $spec = ['has_today', 'has_export', 'is_api', 'cate', 'settle_cycle', 'settle_type'];
    $diyUserAttr = Utils::array_pick( $attr, $spec );
    $attr = Utils::array_omit( $attr, $spec );

    $diyUser = null;
    if ($diyUserAttr) {
      $diyUser = new DiyUserModel(['corp' => $id]);
      $diyUser->fetch();
      try {
        $diyUser->update($diyUserAttr);
      } catch (Exception $e) {
        $this->exit_with_error( $e->getCode(), $e->getMessage(), 400 );
      }
    }

    if ($attr) {
      try {
        $channel->update($attr);
      } catch (Exception $e) {
        $this->exit_with_error($e->getCode(), $e->getMessage(), 400, SQLHelper::$info);
      }
    }

    $diyData = $diyUser ? $diyUser->omit('id') : [];
    $this->output( [
      'code' => 0,
      'msg' => '修改渠道信息成功',
      'channel' => array_merge( $channel->toJSON(), $diyData),
    ] );
  }

  public function delete($id) {
    $attr = array(
      'status' => 1
    );
    $this->update($id, $attr);
  }
}