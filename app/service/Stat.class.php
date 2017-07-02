<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 15/7/17
 * Time: 下午3:59
 */

namespace diy\service;


use PDO;

class Stat extends Base {

  public function get_ad_transfer_by_hour( $filter ) {
    $sql = "SELECT `h`,`transfer_total`
            FROM `s_transfer_stat_ad_h`
            WHERE `transfer_date`=:date AND `ad_id`=:ad_id";
    $DB = $this->get_stat_pdo();
    $state = $DB->prepare($sql);
    $state->execute($filter);
    return $state->fetchAll(PDO::FETCH_KEY_PAIR);
  }

  public function get_ad_click_by_hour( $filter ) {
    $sql = "SELECT `h`,`click_total`
            FROM `s_offer_click_stat_ad_h`
            WHERE `click_date`=:date AND `ad_id`=:ad_id";
    $DB = $this->get_stat_pdo();
    $state = $DB->prepare($sql);
    $state->execute($filter);
    return $state->fetchAll(PDO::FETCH_KEY_PAIR);
  }
}