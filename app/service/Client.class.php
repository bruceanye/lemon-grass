<?php
/**
 * Created by PhpStorm.
 * User: chensheng
 * Date: 2017/9/9
 * Time: 下午4:48
 */

namespace diy\service;

use PDO;


class Client extends Base {
    public function get_clients($page = 0, $pageSize = 0) {
        $DB = $this->get_read_pdo();
        $limit = '';
        if ($pageSize) {
            $pageStart = $pageSize * $page;
            $limit = "\nLIMIT $pageStart,$pageSize";
        }
        $sql = "SELECT *
                FROM `j_client`
                GROUP BY `id`
                $limit";
        $state = $DB->prepare($sql);
        $state->execute();
        $clients = $state->fetchAll(PDO::FETCH_ASSOC);
        return $clients;
    }
}