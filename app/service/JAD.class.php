<?php
/**
 * Created by PhpStorm.
 * User: chensheng
 * Date: 2017/9/24
 * Time: 下午3:05
 */

namespace diy\service;

use diy\utils\Utils;
use PDO;

class JAD extends Base {
    public function get_ads($filters, $page = 0, $pageSize = 0) {
        $DB = $this->get_read_pdo();
        $limit = '';
        if ($pageSize) {
            $pageStart = $pageSize * $page;
            $limit = "\nLIMIT $pageStart,$pageSize";
        }

        $filters['a.status'] = 0;
        list($conditions, $params) = $this->parse_filter( $filters );

        $sql = "SELECT a.*, b.`full_name`
                FROM `j_client_ad` AS a LEFT JOIN `j_agreement` AS b ON a.`agreement_id` = b.id
                WHERE $conditions
                GROUP BY a.`id` DESC 
                $limit";
        $state = $DB->prepare($sql);
        $state->execute($params);
        $clients = $state->fetchAll(PDO::FETCH_ASSOC);
        return $clients;
    }

    public function get_total() {
        $DB = $this->get_read_pdo();

        $sql = "SELECT COUNT('X')
                FROM `j_client_ad`
                WHERE `status` = 0";
        $state = $DB->prepare($sql);
        $state->execute();
        return $state->fetchColumn();
    }

    protected function parse_filter( array $filters = null, array $options = array() ) {
        $defaults = ['to_string' => true];
        $options = array_merge($defaults, $options);
        $spec = array('keyword');
        $pick = Utils::array_pick($filters, $spec);
        $filters = Utils::array_omit($filters, $spec);
        list($conditions, $params) = parent::parse_filter( $filters, array('to_string' => false));
        if ($pick) {
            foreach ($pick as $key => $value) {
                switch ($key) {
                    case 'keyword':
                        if ($value) {
                            $conditions[] = "(`name` LIKE :keyword)";
                            $params[':keyword'] = '%' . $value . '%';
                        }
                        break;
                }
            }
        }
        if ($options['to_string']) {
            $conditions = count($conditions) ? implode(' AND ', $conditions) : 1;
        }
        if (!is_array($conditions) && $conditions && $options['is_append']) {
            $conditions = ' AND ' . $conditions;
        }
        return array($conditions, $params);
    }
}