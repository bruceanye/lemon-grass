<?php
/**
 * Created by PhpStorm.
 * User: chensheng
 * Date: 2017/10/28
 * Time: 下午10:02
 */
namespace diy\service;

use PDO;
use diy\utils\Utils;
use SQLHelper;
use Exception;


class JADTransfer extends Base {
    static $FIELD_CLICK = array('ad_id', 'nums', 'quotes', 'click_date', 'click_rmb', 'market_id');
    const T_AD_CLICK = 't_ad_click';
    const T_AD_MARKET_CLICK = 't_ad_market_click';

    public function get_transfers_by_adid($ad_id) {
        $DB = $this->get_read_pdo();

        $sql = "SELECT a.`name`, b.*
                FROM `j_client_ad` AS a LEFT JOIN `j_ad_transfer` AS b ON a.`id` = b.`ad_id`
                WHERE $ad_id = :ad_id";

        $state = $DB->prepare($sql);
        $state->execute(array(
            ':ad_id' => $ad_id,
        ));
        $result = $state->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    public function getClickAdsByDate($filters, $start, $end) {
        $adService = new AD();
        $ad_id = $filters['ad_id'];
        $adinfo = $adService->get_ad_info_by_id_new($ad_id);

        $DB = $this->get_read_pdo();
        list($conditions, $params) = $this->parse_filter(Utils::array_pick($filters, array('ad_id')));
        $conditions = $conditions ? $conditions . " AND `click_date`>='$start' AND `click_date`<='$end'" : "";
        $sql = "SELECT `click_date`,`nums`,`quotes`,`click_rmb` AS `quote_rmb`
            FROM " . self::T_AD_CLICK . "
            WHERE $conditions
            GROUP BY `click_date`";
        $state = $DB->prepare($sql);
        $state->execute($params);
        $clickStat = $state->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_UNIQUE);

        $quote_rmb = $adinfo['money'];
        $result = array();

        // 汇总
        $result_total = array(
            'date' => '汇总',
            'quote_rmb' => '/',
            'nums' => 0,
            'quotes' => 0,
            'is_amount' => true
        );
        for($stamp = strtotime($end); $stamp >= strtotime($start); $stamp -= 86400) {
            $date = date("Y-m-d", $stamp);
            $adClick = array(
                'date' => $date,
                'quote_rmb' => ($clickStat[$date] ? (int)$clickStat[$date]['quote_rmb'] : $quote_rmb) / 100,
                'nums' => $clickStat[$date] ? (int)$clickStat[$date]['nums'] : 0, // 如果没有录数，采用系统默认的
                'quotes' => $clickStat[$date] ? (int)$clickStat[$date]['quotes'] : 0,
            );
            $result_total['nums'] = $result_total['nums'] + $adClick['nums'];
            $result_total['quotes'] = $result_total['quotes'] + $adClick['quotes'];
            $result[] = $adClick;
        }
        $result = array_reverse($result);

        // 总计的数据加到末尾
        $result[] = $result_total;
        return $result;
    }

    public function getClickMarketsByDate($filters, $start, $end) {
        $adService = new AD();
        $market_id = $filters['market_id'];
        $market_info = $adService->get_market_info_by_id_new($market_id);

        $DB = $this->get_read_pdo();
        list($conditions, $params) = $this->parse_filter(Utils::array_pick($filters, array('market_id')));
        $conditions = $conditions ? $conditions . " AND `click_date`>='$start' AND `click_date`<='$end'" : "";
        $sql = "SELECT `click_date`,`nums`,`quotes`,`click_rmb` AS `quote_rmb`
            FROM " . self::T_AD_MARKET_CLICK . "
            WHERE $conditions
            GROUP BY `click_date`";
        $state = $DB->prepare($sql);
        $state->execute($params);
        $clickStat = $state->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_UNIQUE);

        $quote_rmb = $market_info['price'];
        $result = array();

        // 汇总
        $result_total = array(
            'date' => '汇总',
            'quote_rmb' => '/',
            'nums' => 0,
            'quotes' => 0,
            'is_amount' => true
        );
        for($stamp = strtotime($end); $stamp >= strtotime($start); $stamp -= 86400) {
            $date = date("Y-m-d", $stamp);
            $adClick = array(
                'date' => $date,
                'quote_rmb' => ($clickStat[$date] ? (int)$clickStat[$date]['quote_rmb'] : $quote_rmb) / 100,
                'nums' => $clickStat[$date] ? (int)$clickStat[$date]['nums'] : 0, // 如果没有录数，采用系统默认的
                'quotes' => $clickStat[$date] ? (int)$clickStat[$date]['quotes'] : 0,
            );
            $result_total['nums'] = $result_total['nums'] + $adClick['nums'];
            $result_total['quotes'] = $result_total['quotes'] + $adClick['quotes'];
            $result[] = $adClick;
        }
        $result = array_reverse($result);

        // 总计的数据加到末尾
        $result[] = $result_total;
        return $result;
    }

    public function record ($data, $param) {
        $DB_write = $this->get_write_pdo();
        $ad_operation_service = new ADOperationLogger();
        foreach ($data as $key => $value) {
            if (!isset($value['click_date'])) {
                $value['click_date'] = $param;
            } else if (!isset($value['ad_id'])) {
                $value['ad_id'] = $param;
            }
            $value['click_rmb'] = $value['click_rmb'] * 100;
            $ad_operation_service->add($value['ad_id'], 'click', 'insert', $value['click_date'] . ', ' . $value['nums'] . ', ' . $value['click_rmb']);
            $data[$key] = Utils::array_pick($value, self::$FIELD_CLICK);
            $data[$key]['click_time'] = date("Y-m-d H:i:s");
        }
        if( SQLHelper::insert_update_multi($DB_write, self::T_AD_CLICK, $data)) {
            $ad_operation_service->logAll(ADOperationLogger::SUCCESS);
        } else {
            $ad_operation_service->logAll(ADOperationLogger::FAIL);
            throw new Exception('录入失败', 100);
        }
    }

    public function recordMarket ($data, $param) {
        $DB_write = $this->get_write_pdo();
        $ad_operation_service = new ADOperationLogger();
        foreach ($data as $key => $value) {
            if (!isset($value['click_date'])) {
                $value['click_date'] = $param;
            } else if (!isset($value['market_id'])) {
                $value['ad_id'] = $value['market_id'] = $param;
            }
            $value['click_rmb'] = $value['click_rmb'] * 100;
            $ad_operation_service->add($value['market_id'], 'click', 'insert', $value['click_date'] . ', ' . $value['nums'] . ', ' . $value['click_rmb']);
            $data[$key] = Utils::array_pick($value, self::$FIELD_CLICK);
            $data[$key]['click_time'] = date("Y-m-d H:i:s");
        }
        if( SQLHelper::insert_update_multi($DB_write, self::T_AD_MARKET_CLICK, $data)) {
            $ad_operation_service->logAll(ADOperationLogger::SUCCESS);
        } else {
            $ad_operation_service->logAll(ADOperationLogger::FAIL);
            throw new Exception('录入失败', 100);
        }
    }
}