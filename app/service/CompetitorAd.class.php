<?php
/**
 * Created by PhpStorm.
 * User: hemengyuan
 * Date: 15/5/19
 * Time: 上午11:10
 */

namespace diy\service;

use PDO;

class CompetitorAd extends Base {

  protected function get_my_competitor_ads() {
    $DB = $this->get_read_pdo();
    $me = $_SESSION['id'];
    $sql = "SELECT *
            FROM `t_competitor_ads_delivery`
            WHERE `owner`=$me AND `status`=0";
    $result = $DB->query($sql)->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_UNIQUE);
    foreach ( $result as $pack_name => $info ) {
      $info['communication'] = (int)$info['communication'];
      $info['delivery'] = (int)$info['delivery'];
      $info['important'] = (int)$info['important'];
      $result[$pack_name] = $info;
    }
    return $result;
  }

  public function get_competitor_ads_stat($order, $seq, $keyword) {
    $delivery_info = self::get_my_competitor_ads();
    $keys = array_keys($delivery_info);
    $data = file_get_contents(INNER_SERVICE . 'inner_api/potential_ad/' . implode(',', $keys));
    $data = json_decode($data, true);
    $ads = $data['data'];

    $result = array();
    $dj_status = $_REQUEST['dj_status'];
    $fund_status = $_REQUEST['fund_status'];
    foreach ($delivery_info as $pack_name => $value) {
      $info = $ads[$pack_name];
      if (!$info) {
        continue;
      }
      if ($keyword && strpos($info['app_name'], $keyword) === false) {
        continue;
      }
      if (isset($dj_status) && $info['dj_status'] != $dj_status) {
        continue;
      }
      if (isset($fund_status) && $info['fund_status'] != $fund_status) {
        continue;
      }
      $value = array_merge($value, $info);
      $result[] = $value;
    }

    if ($order) {
      if ($seq == 'desc') {
        function build_sorter($order) {
          return function ($a, $b) use ($order) {
            if (is_numeric($a[$order])) {
              return $b[$order] - $a[$order];
            }
            return strcmp($b[$order], $a[$order]);
          };
        }
        usort($result, build_sorter($order));
      } else {
        function build_sorter($order) {
          return function ($a, $b) use ($order) {
            if (is_numeric($a[$order])) {
              return $a[$order] - $b[$order];
            }
            return strcmp($a[$order], $b[$order]);
          };
        }
        usort($result, build_sorter($order));
      }
    } else {
      usort($result, function($a, $b) {if ($a['important'] != $b['important']) {return (int)$b['important'] - (int)$a['important'];} if ($a['source_num'] != $b['source_num']) {return (int)$b['source_num'] - (int)$a['source_num'];} return strcmp($b['update_time'], $a['update_time']);});
    }

    return $result;
  }

  public function check_delivery_ad_owner($pack_name, $me) {
    $sql = "SELECT 'X'
            FROM `t_competitor_ads_delivery`
            WHERE `pack_name`=:pack_name AND `owner`=$me";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $state->execute(array(':pack_name' => $pack_name));
    return $state->fetch(PDO::FETCH_ASSOC);
  }

  public function get_ad_info_by_pack_name($pack_name) {
    $sql = "select app_name,category,corp from t_market_ad_info where pack_name=:pack_name";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $state->execute(array(':pack_name' => $pack_name));
    return $state->fetch(PDO::FETCH_ASSOC);
  }

  public function get_companies_by_pack_name($pack_name) {
    $sql = "select company from t_competitors_ads where pack_name=:pack_name and platform='android' and `current_time`>=curdate()";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $state->execute(array(':pack_name' => $pack_name));
    return $state->fetchAll(PDO::FETCH_COLUMN);
  }

  public function get_delivered_channel_by_pack_name($pack_name) {
    $sql = "select c.channel from s_transfer_stat_ad as a join t_adinfo as b on a.ad_id=b.id join t_ad_source as c on b.id=c.id where b.pack_name=:pack_name and a.transfer_date>=date_sub(curdate(),interval 60 day) group by c.channel";
    $DB = $this->get_read_pdo();
    $state = $DB->prepare($sql);
    $state->execute(array(':pack_name' => $pack_name));
    return $state->fetchAll(PDO::FETCH_COLUMN);
  }

  public function get_competitor_ads_number() {
  }
}