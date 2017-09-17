<?php
/**
 * Created by PhpStorm.
 * User: chensheng
 * Date: 2017/9/10
 * Time: 上午11:52
 */
namespace diy\service;

use PDO;
use diy\utils\Utils;


class JChannel extends Base {
    public function get_channels($filters, $page = 0, $pageSize = 0) {
        $DB = $this->get_read_pdo();
        $limit = '';
        if ($pageSize) {
            $pageStart = $pageSize * $page;
            $limit = "\nLIMIT $pageStart,$pageSize";
        }

        $filters['status'] = 0;
        list($conditions, $params) = $this->parse_filter( $filters );

        $sql = "SELECT *
                FROM `j_channel`
                WHERE $conditions
                GROUP BY `id`
                $limit";
        $state = $DB->prepare($sql);
        $state->execute($params);
        $clients = $state->fetchAll(PDO::FETCH_ASSOC);
        return $clients;
    }

    public function get_total() {
        $DB = $this->get_read_pdo();

        $sql = "SELECT COUNT('X')
                FROM `j_channel`
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