<?php
/**
 * Created by PhpStorm.
 * User: chensheng
 * Date: 2017/9/16
 * Time: 下午5:48
 */

namespace diy\service;

use PDO;


class JAgreement extends Base {
    public function get_agreements($page = 0, $pageSize = 0) {
        $DB = $this->get_read_pdo();
        $limit = '';
        if ($pageSize) {
            $pageStart = $pageSize * $page;
            $limit = "\nLIMIT $pageStart,$pageSize";
        }
        $sql = "SELECT *
                FROM `j_agreement`
                WHERE `status` = 0
                GROUP BY `id`
                $limit";
        $state = $DB->prepare($sql);
        $state->execute();
        $clients = $state->fetchAll(PDO::FETCH_ASSOC);
        return $clients;
    }
}