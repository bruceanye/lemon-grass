<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 16/6/1
 * Time: 下午6:35
 */

namespace diy\model;


use diy\utils\Utils;
use PDO;

class AgreementChangeLogCollection extends Collection {
  const SPEC = ['keyword', 'start', 'end'];


  /**
   * 将合同变更的信息附加于广告信息之上
   *
   * @param $ad_info
   * @param $start
   * @param string $key
   *
   * @return array
   */
  public function addToAD( $ad_info, $start, $key = 'id' ) {
    $agreements = $this->toJSON();
    return array_map(function ($ad) use ($agreements, $start, $key) {
      $ad_id = $ad[$key];
      if ($agreements[ $ad_id ]) {
        $agreement = $agreements[$ad_id][0];
        if ($agreement['date'] <= $start) {
          $ad['aid'] = $agreement['aid'];
          $ad['channel'] = $agreement['company_short'];
          $ad['full_name'] = $agreement['company'];
          $ad['agreement_date'] = $agreement['date'];
          array_shift($agreements[$ad_id]);
        }
        $ad['agreements'] = $agreements[$ad_id];
      }
      return $ad;
    }, $ad_info);
  }

  public function fetch( $page = 0, $pageSize = 0, $is_map = false  ) {
    $this->get_read_pdo();
    $this->filters = $this->move_field_to($this->filters, 'agreement_id', 'd');
    $this->filters = $this->move_field_to($this->filters, 'ad_name', 'c');
    list($conditions, $params) = $this->parse_filter($this->filters, [
      'spec' => self::SPEC,
    ]);
    $limit = '';
    if ($pageSize) {
      $start = $page * $pageSize;
      $limit = "LIMIT $start,$pageSize";
    }
    $sql = "SELECT a.*,`company`,`company_short`,b.`agreement_id` AS `aid`,c.`ad_name`,
              `cid`,`NAME` AS `admin`
            FROM `t_ad_agreement_change_log` a
              LEFT JOIN `t_agreement` b ON a.`agreement_id`=b.`id`
              LEFT JOIN `t_adinfo` c ON a.`ad_id`=c.`id`
              LEFT JOIN `t_ad_source` d ON a.`ad_id`=d.`id`
              LEFT JOIN `t_admin` e ON a.`admin`=e.`id`
            WHERE $conditions
            $limit";
    $state = $this->DB->prepare($sql);
    $state->execute($params);
    $this->items = $state->fetchAll(PDO::FETCH_ASSOC);
    if (is_array($this->items)) {
      $this->items = array_map(function ($log) {
        $log['is_correct'] = (int)$log['is_correct'];
        return $log;
      }, $this->items);
    }
  }

  /**
   * 取这段时间变动过的广告
   */
  public function fetchLinkedAD() {
    $this->get_read_pdo();
    $filters = Utils::array_pick($this->filters, 'keyword', 'agreement_id');
    $this->filters = Utils::array_omit($this->filters, 'agreement_id', 'keyword');
    list($maybe, $maybe_params) = $this->parse_filter($filters, [
      'to_string' => false,
      'no_ad_name' => true,
      'spec' => self::SPEC,
    ]);
    list($must, $params) = $this->parse_filter($this->filters, [ 'spec' => self::SPEC ]);
    $maybe = $maybe ? implode(' OR ', $maybe) : 1;
    $sql = "SELECT `ad_id`
            FROM `t_ad_agreement_change_log` a
              JOIN `t_agreement` b ON a.`agreement_id`=b.`id`
            WHERE ($maybe) AND {$must}";
    $state = $this->DB->prepare($sql);
    $state->execute(array_merge($maybe_params, $params));
    return $state->fetchAll(PDO::FETCH_COLUMN);
  }

  /**
   * 取一段时间来的新增关联合同
   *
   * @param $start
   * @param $end
   *
   * @return array
   */
  public function fetchRange( $start, $end ) {
    $this->fetch();
    $items = [];
    // 先按广告id分组
    foreach ( $this->items as $item ) {
      $items[$item['ad_id']][] = $item;
    }
    // 然后分成开始>中间>结束的组
    $items = array_map(function ($agreements) use ($start, $end) {
      $record = [ 'in' => [] ];
      foreach ( $agreements as $agreement ) {
        if ($agreement['date'] <= $start) { // 每月一号也算之前
          $record['before'] = $agreement;
        } else if ($agreement['date'] > $end) {
          $record['after'] = $agreement;
          break;
        } else {
          $record['in'][$agreement['date']] = $agreement; // 同样的一天只保留最后一条记录
        }
      }
      array_unshift($record['in'], $record['before']);
      array_push($record['in'], $record['after']);
      $record = array_values(array_filter($record['in']));
      for ($i = 0, $len = count($record) - 1; $i < $len; $i++) {
        $record[$i]['end'] = date('Y-m-d', strtotime($record[$i + 1]['date']) - 86400);
      }
      return array_values($record);
    }, $items);
    $this->items = $items;
    return $items;
  }
  
  public function size() {
    $this->get_read_pdo();
    list($conditions, $params) = $this->parse_filter($this->filters);
    $sql = "SELECT COUNT('x')
            FROM `t_ad_agreement_change_log` a
              LEFT JOIN `t_agreement` b  ON a.`agreement_id`=b.`id`
              LEFT JOIN `t_adinfo` c ON a.`ad_id`=c.`id`
            WHERE $conditions";
    $state = $this->DB->prepare($sql);
    $state->execute($params);
    return $state->fetchColumn();
  }

  protected function parseSpecialFilter( $spec, $options = null ) {
    $conditions = $params = [];
    $no_ad_name = $options['no_ad_name'];
    foreach ( $spec as $key => $value ) {
      switch ($key) {
        case 'keyword':
          if ($value) {
            $conditions[] = '(b.`agreement_id` LIKE :keyword OR `company` LIKE :keyword OR `company_short` LIKE :keyword
              or a.`ad_id`=:keyword_ad_id' . ($no_ad_name ? '' : ' OR c.`ad_name` LIKE :keyword'). ')';
            $params[':keyword'] = '%' . $value . '%';
            $params[':keyword_ad_id'] = $value;
          }
          break;

        case 'start':
          $conditions[] = '`date`>=:start';
          $params[':start'] = $value;
          break;

        case 'end':
          $conditions[] = '`date`<=:end';
          $params[':end'] = $value;
          break;
      }
    }
    return [$conditions, $params];
  }
}