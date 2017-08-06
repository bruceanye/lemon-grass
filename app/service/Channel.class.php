<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 15/5/19
 * Time: 下午3:25
 */

namespace diy\service;


use diy\model\DiyUserModel;
use PDO;
use diy\utils\Utils;

class Channel extends Base {

  public function get_channel_ads_nums($filters, $start, $end) {
    $DB = $this->get_read_pdo();

    list($conditions, $params) = $this->parse_filter($filters);
    $sql = "SELECT ifnull(d.`company_short`,ifnull(c.`alias`,b.`channel`)) as channel,count('X') AS nums
            FROM `t_adinfo` a
              LEFT JOIN `t_ad_source` b ON a.`id` = b.`id`
              LEFT JOIN `t_channel_map` c ON b.`channel` = c.`id`
              LEFT JOIN `t_agreement` d ON b.`agreement_id` = d.`id`
            WHERE `create_time` >= '$start' AND create_time <= '$end'
            WHERE $conditions
            GROUP BY ifnull(d.`company_short`,ifnull(c.`alias`,b.`channel`))";
    $state = $DB->prepare($sql);
    $state->execute($params);
    return $state->fetchAll(PDO::FETCH_KEY_PAIR);
  }

  public function get_channel_ads($filters) {
    $DB = $this->get_read_pdo();

    list($conditions, $params) = $this->parse_filter($filters);
    $sql = "SELECT ifnull(d.company_short, ifnull(c.alias,b.channel)) AS channel,`feedback`,a.ad_name,count('X') as nums,a.`id`
            FROM `t_adinfo` AS a
            LEFT JOIN `t_ad_source` AS b ON a.`id` = b.`id`
            LEFT JOIN `t_channel_map` AS c ON b.`channel` = c.id
            LEFT JOIN `t_agreement` AS d ON b.`agreement_id` = d.id
            WHERE $conditions
            GROUP BY `feedback`,`channel`";
    $state = $DB->prepare($sql);
    $state->execute($params);
    return $state->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_GROUP);
  }

  public function get_channel_payment($filters, $start, $end) {
    $DB = $this->get_read_pdo();

    list($conditions, $params) = $this->parse_filter($filters);
    $sql = "SELECT ifnull(d.`company_short`,ifnull(c.`alias`,b.`channel`)) AS channel,
            a.`id`,a.`rmb`,`paid_time`,`invoice_time`
            FROM `t_ad_payment` a
            LEFT JOIN `t_ad_source` b ON a.`id` = b.`id`
            LEFT JOIN `t_channel_map` c ON b.`channel` = c.`id`
            LEFT JOIN `t_agreement` d ON b.`agreement_id` = d.`id`
            WHERE `month`>='$start' AND `month`<='$end' AND $conditions";
    $state = $DB->prepare($sql);
    $state->execute($params);
    return $state->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_GROUP);
  }

  public function get_channel_quote($filters, $start, $end) {
    $DB = $this->get_read_pdo();

    list($conditions, $params) = $this->parse_filter($filters);
    $sql = "SELECT ifnull(d.`company_short`,ifnull(c.`alias`,b.`channel`)) AS channel,a.`ad_id`,a.`quote_rmb`,`nums`
            FROM `t_adquote` a
            LEFT JOIN `t_ad_source` b ON a.ad_id = b.id
            LEFT JOIN `t_channel_map` c ON b.channel = c.id
            LEFT JOIN `t_agreement` d ON b.agreement_id = d.id
            WHERE quote_date >= '$start' AND quote_date <= '$end' AND $conditions";
    $state = $DB->prepare($sql);
    $state->execute($params);
    return $state->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_GROUP);
  }

  /**
   * 返回渠道信息，暂时只有报备邮件那里用，所以按照以前返回简称即可
   *
   * @param array $filters
   * @param int $page optional 页码
   * @param int $pageSize optional 每页数量
   *
   * @return string
   */
  public function get_channel( array $filters, $page = 0, $pageSize = 0 ) {
    $DB = $this->get_read_pdo();
    $filters = $this->move_field_to($filters, 'id', 'a');
    $filters = $this->move_field_to( $filters, 'owner', 'c' );
    $filters['d.status'] = DiyUserModel::ALLOW_STATUS;
    list($conditions, $params) = $this->parse_filter( $filters );
    $limit = '';
    if ($pageSize) {
      $pageStart = $pageSize * $page;
      $limit = "\nLIMIT $pageStart,$pageSize";
    }
    $sql = "SELECT a.`id`,`full_name`,`alias`,d.`type`,`prepaid`,`is_api`,`cate`,`settle_cycle`,
              `settle_type`,d.`id` AS `diy_id`,`has_export`,`has_today`
            FROM `t_channel_map` a
              JOIN `t_agreement` c ON a.`id`=c.`channel_id`
              LEFT JOIN `t_diy_user` d ON a.`id`=d.`corp`
            WHERE $conditions
            GROUP BY a.`id`
            $limit";
    $state = $DB->prepare($sql);
    $state->execute($params);
    $channels = $state->fetchAll(PDO::FETCH_ASSOC);
    return array_map( function ($channel) {
      $channel['has_export'] = (int)$channel['has_export'];
      $channel['has_today'] = (int)$channel['has_today'];
      return $channel;
    }, $channels );
  }

  public function get_base_channel() {
      $DB = $this->get_read_pdo();
      $sql = "SELECT `id`,`company_name`,`user`,`address`,`email`,`telephone`,`comment`
              FROM `t_channel`";
      $state = $DB->prepare($sql);
      $state->execute([]);
      return $state->fetchAll(PDO::FETCH_ASSOC);
  }

  public function get_channel_num( $filters ) {
    $DB = $this->get_read_pdo();
    list($conditions, $params) = $this->parse_filter($filters);
    $sql = "SELECT COUNT('X')
            FROM `t_channel_map` a
              JOIN `t_agreement` c ON a.`id`=c.`channel_id`
            WHERE $conditions";
    $state = $DB->prepare($sql);
    $state->execute($params);
    return $state->fetchColumn();
  }

  public function get_fullname($id) {
    $DB = $this->get_read_pdo();
    $sql = "SELECT `full_name`
            FROM `t_channel_map`
            WHERE `id`=:id";
    $state = $DB->prepare($sql);
    $state->execute(array(':id' => $id));
    return $state->fetchColumn();
  }

  public function get_prepaid( $id, $page, $pageSize ) {
    $DB = $this->get_read_pdo();
    $start = $page * $pageSize;
    $sql = "SELECT a.`id`,a.`create_time`,`admin_id`,a.`rmb`,`payment_id`,
              b.`NAME` AS `admin_name`,`ad_name`,`cid`
            FROM `t_pre_paid` a
              LEFT JOIN `t_admin` b ON a.`admin_id`=b.`id`
              LEFT JOIN `t_ad_payment` c ON a.`payment_id`=c.`id` 
              LEFT JOIN `t_adinfo` d ON c.`id`=d.`id`
              LEFT JOIN `t_ad_source` e ON c.`id`=e.`id`
            WHERE `type`=0 AND `channel_id`=:id
            LIMIT $start,$pageSize";
    $state = $DB->prepare($sql);
    $state->execute([':id' => $id]);
    return $state->fetchAll(PDO::FETCH_ASSOC);
  }

  public function get_prepaid_num( $id ) {
    $DB = $this->get_read_pdo();
    $sql = "SELECT COUNT('X')
            FROM `t_pre_paid`
            WHERE `type`=0 AND `channel_id`=:id";
    $state = $DB->prepare($sql);
    $state->execute([':id' => $id]);
    return $state->fetchColumn();
  }

  /**
   * @param array $filters
   * @param array $options
   *
   * @return array
   */
  protected function parse_filter( array $filters = null, array $options = array() ) {
    $defaults = ['to_string' => true];
    $options = array_merge($defaults, $options);

    $spec = array('keyword','salesman','channel');
    $pick = Utils::array_pick($filters, $spec);
    $filters = Utils::array_omit($filters, $spec);
    list($conditions, $params) = parent::parse_filter( $filters, array('to_string' => false));
    foreach ($pick as $key => $value) {
      switch ($key) {
        case 'keyword':
          if ($value) {
            $conditions[] = "(`alias` LIKE :keyword OR `full_name` LIKE :keyword)";
            $params[':keyword'] = "%$value%";
          }
          break;

        case 'salesman':
          if ($value) {
            $conditions[] = "(b.`owner`=:salesman OR b.`execute_owner`=:salesman)";
            $params[':salesman'] = $value;
          }
          break;

        case 'channel':
          if ($value) {
            $conditions[] = " (ifnull(d.`company_short`,ifnull(c.`alias`,b.`channel`)))=:channel";
            $params[':channel'] = $value;
          }
          break;
      }
    }
    $conditions = $options['to_string'] ? ($options['is_append'] ? ' and ' : '') . implode(' AND ', $conditions) : $options;
    return array($conditions, $params);
  }
}