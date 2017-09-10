<?php
/**
 * Created by PhpStorm.
 * User: chensheng
 * Date: 2017/9/10
 * Time: 上午11:52
 */
namespace diy\service;

use PDO;


class JChannel extends Base {
    public function get_channels($page = 0, $pageSize = 0) {
        $DB = $this->get_read_pdo();
        $limit = '';
        if ($pageSize) {
            $pageStart = $pageSize * $page;
            $limit = "\nLIMIT $pageStart,$pageSize";
        }
        $sql = "SELECT *
                FROM `j_channel`
                GROUP BY `id`
                $limit";
        $state = $DB->prepare($sql);
        $state->execute();
        $clients = $state->fetchAll(PDO::FETCH_ASSOC);
        return $clients;
    }
}