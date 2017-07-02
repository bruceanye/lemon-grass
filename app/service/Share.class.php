<?php
/**
 * Created by PhpStorm.
 * User: chensheng
 * Date: 15/11/16
 * Time: 上午10:27
 */

namespace diy\service;

use PDO;

class Share extends Base {
  public function get_share_combo_rmb_stat($start, $end, $filters) {
    list($conditions, $params) = $this->parse_filter($filters, ['is_append' => true]);
    $DB = $this->get_stat_pdo();
    $sql = "SELECT `ad_id`,sum(`combo_rmb`)
            FROM `s_ppjoy_stat_ad`
            WHERE `date`>=:start AND `date`<=:end $conditions
            GROUP BY `ad_id`";
    $state = $DB->prepare($sql);
    $state->execute(array_merge($params, [
      ':start' => $start,
      ':end' => $end,
    ]));
    return $state->fetchAll(PDO::FETCH_KEY_PAIR);
  }

  public function get_share_ad_combo_rmb($id, $start, $end) {
    $DB    = $this->get_stat_pdo();
    $sql   = "SELECT `date`,combo_rmb
            FROM s_ppjoy_stat_ad
            WHERE `date`>=:start AND `date`<=:end AND ad_id=:id";
    $state = $DB->prepare( $sql );
    $state->execute( array(
      ':start' => $start,
      ':end'   => $end,
      ':id'    => $id,
    ) );

    return $state->fetchAll( PDO::FETCH_KEY_PAIR );
  }
}