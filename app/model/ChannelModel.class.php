<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 15/5/19
 * Time: 上午11:01
 */

namespace diy\model;


use diy\error\Error;
use diy\service\Channel;
use diy\utils\Utils;
use Exception;
use PDO;
use SQLHelper;

/**
 * @property string alias 简称
 * @property string full_name 全称
 * @property int type 类型
 * @property int prepaid 预收款余额 
 */
class ChannelModel extends Base {
  static $TABLE = 't_channel_map';
  static $CHANNEL_TABLE = 't_channel';
  static $TYPE = array('无', 'CP', '网盟', '个人', '换量', '开发者', '外放渠道', '其他');
  static $CRM_TARGET = array('score_nums','score_feedback','score_cycle','payment_rate','cut_rate','frequency');
  static $CHANNEL_ATTR = array('address','comment','user','company_name','email');

    static $FEEDBACK_SCORES = array(
    '1' => 15,
    '2' => 20,
    '3' => 20,
    '4' => 25,
    '6' => 25,
    '7' => 30
  );
  static $MAX_SCORES = array(
    'feedback' => 30,
    'nums' => 30,
    'cycle' => 30,
    'quantity' => 30,
    'payment_rate' => 30,
    'cut_rate' => 30
  );

  public static function judge_nums($nums) {
    if ($nums <= 10) {
      $score = 1;
    } else if ($nums <= 20) {
      $score = 10;
    } else if ($nums <= 30) {
      $score = 20;
    } else {
      $score = 30;
    }
    return $score;
  }

  public static function judge_days($days) {
    if ($days <= 1) {
      $score = 30;
    } else if ($days <= 7) {
      $score = 25;
    } else if ($days <= 14) {
      $score = 25;
    } else if ($days <= 30) {
      $score = 20;
    } else {
      $score = 0;
    }
    return $score;
  }

  public static function judge_payment($payment_rate) {
    if ($payment_rate < 0.5) {
      $score = 1;
    } else if ($payment_rate < 0.7) {
      $score = 10;
    } else if ($payment_rate < 0.9) {
      $score = 20;
    } else {
      $score = 30;
    }
    return $score;
  }

  public static function judge_quantity($nums) {
    if ($nums <= 10) {
      $score = 1;
    } else if ($nums <= 20) {
      $score = 10;
    } else if ($nums <= 30) {
      $score = 20;
    } else {
      $score = 30;
    }
    return $score;
  }

  public static function judge_cut($cut_rate) {
    if ($cut_rate < 0.1) {
      $score = 30;
    } else if ($cut_rate < 0.3) {
      $score = 20;
    } else if ($cut_rate < 0.5) {
      $score = 10;
    } else {
      $score = 1;
    }
    return $score;
  }

  public static function judge_total($total) {
    if ($total < 2.83) {
      $tips = '信用程度差，请留意。';
    } else if ($total < 4.33) {
      $tips = '信用程度一般，可合作。';
    } else if ($total < 6) {
      $tips = '信用程度高，值得合作。';
    } else {
      $tips = '信用程度最高，十分值得合作。';
    }
    return $tips;
  }

  public static function get_crm_res() {
    $result = array();
    $now = date('Y-m-d');
    $three_months_before = date('Y-m-d', strtotime('-3 months'));
    $filters = array(
      'salesman' => $_SESSION['id']
    );

    $channel_service = new Channel();
    $channel_list = $channel_service->get_channel_ads($filters);
    $payment_list = $channel_service->get_channel_payment($filters, $three_months_before, $now);
    $quote_list = $channel_service->get_channel_quote($filters, $three_months_before, $now);
    $quantity_list = $channel_service->get_channel_ads_nums($filters, $three_months_before, $now);

    foreach ($quote_list as $key => $quote) {
      $total = 0;
      foreach ($quote as $item) {
        $quote_rmb = $item['quote_rmb'];
        $nums = $item['nums'];
        $total += (int)$quote_rmb * (int)$nums;
      }
      $result[$key] = array(
        'total' => $total
      );
    }

    foreach ($payment_list as $key => $payment) {
      $cycle_total = 0;
      $rmb_total = 0;
      foreach ($payment as $item) {
        $paid_time = $item['paid_time'];
        $invoice_time = $item['invoice_time'];
        $cycle_days = Utils::calculate_date($paid_time, $invoice_time);
        $cycle_total += self::judge_days($cycle_days);
        $rmb_total += $item['rmb'];
      }
      $quote_nums = count($payment);
      $score_cycle = round((int)$cycle_total / (ChannelModel::$MAX_SCORES['cycle'] * $quote_nums), 2);
      $result[$key] = array_merge($result[$key], array(
        'score_cycle' => $score_cycle,
        'rmb' => $rmb_total
      ));
      if (isset($result[$key]['total'])) {
        $payment_rate = round($result[$key]['rmb'] / $result[$key]['total'], 2);
        $score_payment_rate = round((int)self::judge_payment($payment_rate) / ChannelModel::$MAX_SCORES['payment_rate'], 2);
        $cut_rate = 1 - $payment_rate;
        $score_cut_rate = round((int)self::judge_payment($cut_rate) / ChannelModel::$MAX_SCORES['cut_rate'], 2);
        $result[$key] = array_merge($result[$key], array(
          'payment_rate' => $score_payment_rate,
          'cut_rate' => $score_cut_rate
        ));
      }
    }

    foreach ($channel_list as $key => $channel) {
      $nums = 0;
      foreach ($channel as $item) {
        $feedback = $item['feedback'];
        $nums += $item['nums'];
        if (in_array($feedback, array_keys(ChannelModel::$FEEDBACK_SCORES))) {
          $result[$key]['score_feedback'] += ChannelModel::$FEEDBACK_SCORES[$feedback] * $item['nums'];
        }
      }
      $result[$key]['nums'] = $nums;
      $score_nums = round((int)self::judge_nums($nums) / ChannelModel::$MAX_SCORES['nums'], 2);
      $score_feedback = round((int)$result[$key]['score_feedback'] / (ChannelModel::$MAX_SCORES['feedback'] * $nums), 2);

      $result[$key] = array_merge($result[$key], array(
        'score_nums' => $score_nums,
        'score_feedback' => $score_feedback
      ));
    }

    foreach ($quantity_list as $key => $quantity) {
      $score = self::judge_quantity($quantity);
      if (isset($result[$key])) {
        $result[$key] = array_merge($result[$key], array(
          'frequency' => round((int)$score / ChannelModel::$MAX_SCORES['quantity'], 2)
        ));
      }
    }
    return $result;
  }

  public function save(array $attr = null) {
    $DB = $this->get_write_pdo();
    $me = $_SESSION['id'];

    // 判断该简称是否已经存在
    $sql = "SELECT `id`, `alias`, `full_name`, `type`, `sales`
            FROM `" . self::$TABLE . "`
            WHERE `full_name`=:full_name AND `status`=0 AND `sales`=$me";
    $state = $DB->prepare($sql);
    $state->execute(array(':full_name' => $this->get('full_name')));
    $data = $state->fetch(PDO::FETCH_ASSOC);
    if ($data) {
      if ($data['type'] != $this->get('type')) {
        throw new Exception('该公司已被注册为不同类型。', 10);
      }

      if ($data['sales'] != $me) {
        throw new Exception('该公司已被其它商务注册过。', 11);
      }

      $this->id = $this->attributes['id'] = $data['id'];
      return true;
    }

    SQLHelper::insert($DB, self::$TABLE, $this->attributes);
    $this->id = $this->attributes['id'] = SQLHelper::$lastInsertId;
    return true;
  }

    public function save_new(array $attr = null) {
        $DB = $this->get_write_pdo();

        $attr = Utils::array_pick($attr, self::$CHANNEL_ATTR);

        SQLHelper::insert($DB, self::$CHANNEL_TABLE, $attr);
        return true;
    }

  public function update(array $attr = null) {
    $attr = $this->validate($attr);
    if (!$attr) {
      return $this;
    }

    if ($attr['full_name']) {
      $flag = $this->is_full_name_exists($attr['full_name']);
      if ($flag) {
        throw new Exception('该公司已被注册过，请重新填写。', 12);
      }
    }

    $DB = $this->get_write_pdo();
    $result = SQLHelper::update($DB, self::$TABLE, $attr, $this->id, false);
    if ($result === false) {
      throw new Exception('更新渠道信息失败。', 13);
    }
    $this->attributes = array_merge($this->attributes, $attr);
    return $this;
  }

    public function update_channel(array $attr = null) {
        $attr = $this->validate($attr);
        if (!$attr) {
            return $this;
        }

        $DB = $this->get_write_pdo();
        $result = SQLHelper::update($DB, self::$CHANNEL_TABLE, $attr, $this->id, false);
        if ($result === false) {
            throw new Exception('更新广告主信息失败。', 13);
        }
        $this->attributes = array_merge($this->attributes, $attr);
        return $this;
    }

  public function is_full_name_exists($full_name) {
    $DB = $this->get_read_pdo();
    $me = $_SESSION['id'];
    $sql = "SELECT COUNT('X')
            FROM " . self::$TABLE . "
            WHERE `full_name`=:full_name AND `sales`=$me AND `status`=0";
    $state = $DB->prepare($sql);
    $state->execute(array(':full_name' => $full_name));
    return $state->fetchColumn();
  }

  public function fetch(  ) {
    $service = new Channel();
    $attr = $service->get_channel(['id' => $this->id])[0];
    $this->attributes = $attr;
  }

  public function fetchPrepaid( $page, $pageSize ) {
    $service = new Channel();
    return $service->get_prepaid($this->id, $page, $pageSize);
  }

  public function prepaidLength() {
    $service = new Channel();
    return $service->get_prepaid_num($this->id);
  }

  protected function validate(array $attr = null) {
    // 防XSS
    $attr = Utils::array_strip_tags($attr);

    if ($attr['id']) {
      $this->id = $attr['id'];
    }

    if (!$attr['id'] && (!$attr['full_name'] || !$attr['alias'])) {
      $this->error = new Error(20, '缺少关键数据。', 400);
    }

    return $attr;
  }
}