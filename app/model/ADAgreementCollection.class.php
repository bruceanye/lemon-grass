<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 16/6/13
 * Time: 下午2:09
 */

namespace diy\model;


use diy\utils\Utils;
use PDO;

class ADAgreementCollection extends Collection {
  const ADD = 0;
  const CORRECT = 1;
  
  public function fetch( $page = 0, $pageSize = 20, $is_map = false ) {
    $this->get_read_pdo();
    list($conditions, $params) = $this->parse_filter($this->filters, ['is_append' => true]);
    $limit = '';
    if ($pageSize) {
      $start = $page * $pageSize;
      $limit = "LIMIT $start,$pageSize";
    }
    $sql = "SELECT a.*,`company`,b.`agreement_id` AS `aid`,c.`ad_name`,`cid`,`NAME` AS `admin`
            FROM `t_ad_agreement_change_log` a
              LEFT JOIN `t_agreement` b ON a.`agreement_id`=b.`id`
              LEFT JOIN `t_adinfo` c ON a.`ad_id`=c.`id`
              LEFT JOIN `t_ad_source` d ON a.`ad_id`=d.`id`
              LEFT JOIN `t_admin` e ON a.`admin`=e.`id`
            WHERE `is_correct`=:type $conditions
            $limit";
    $state = $this->DB->prepare($sql);
    $params[':type'] = self::ADD;
    $state->execute($params);
    $this->items = $state->fetchAll(PDO::FETCH_ASSOC);
    if (is_array($this->items)) {
      $this->items = array_map(function ($log) {
        $log['is_correct'] = (int)$log['is_correct'];
        return $log;
      }, $this->items);
    }
  }

  public function size() {
    $this->get_read_pdo();
    list($conditions, $params) = $this->parse_filter($this->filters, ['is_append' => true]);
    $sql = "SELECT COUNT('x')
            FROM `t_ad_agreement_change_log` a
              LEFT JOIN `t_agreement` b  ON a.`agreement_id`=b.`id`
              LEFT JOIN `t_adinfo` c ON a.`ad_id`=c.`id`
            WHERE `is_correct`=:type $conditions";
    $state = $this->DB->prepare($sql);
    $params[':type'] = self::ADD;
    $state->execute($params);
    return $state->fetchColumn();
  }

  protected function parse_filter( array $filters = null, array $options = null ) {
    $defaults = ['to_string' => true];
    $options = array_merge($defaults, $options);
    $this->move_field_to($filters, 'ad_name', 'c');
    $spec = Utils::array_pick($filters, 'keyword');
    $filters = Utils::array_omit($filters, 'keyword');
    list($conditions, $params) = parent::parse_filter($filters, ['to_string' => false]);
    foreach ( $spec as $key => $value ) {
      switch ($key) {
        case 'keyword':
          if ($value) {
            $conditions[] = '(b.`agreement_id` LIKE :keyword OR `company` LIKE :keyword OR `company_short` LIKE :keyword OR c.`ad_name` LIKE :keyword)';
            $params[':keyword'] = '%' . $value . '%';
          }
          break;
      }
    }
    return $this->outputFilter($conditions, $params, $options);
  }
}