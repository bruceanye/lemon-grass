<?php
/**
 * Created by PhpStorm.
 * User: chensheng
 * Date: 2017/9/9
 * Time: 下午3:55
 */

namespace diy\model;

use SQLHelper;
use diy\utils\Utils;



class ClientModel extends Base {
    static $ATTRIBUTES = array('name', 'company','weibo','address','telephone','birthday','comment');


    public function save(array $attr = null) {
        $DB = $this->get_write_pdo();


        $filters = Utils::array_pick($this->attributes, self::$ATTRIBUTES);
        SQLHelper::insert($DB, 'j_client', $filters);
        $this->id = $this->attributes['id'] = SQLHelper::$lastInsertId;
        return true;
    }
}