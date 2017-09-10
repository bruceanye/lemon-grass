<?php
/**
 * Created by PhpStorm.
 * User: chensheng
 * Date: 2017/9/10
 * Time: 上午11:23
 */

namespace diy\model;

use diy\utils\Utils;
use SQLHelper;
use Exception;


class JChannelModel extends Base {
    static $ATTRIBUTES = array('name', 'company','weibo','address','telephone','birthday','comment');


    public function save(array $attr = null) {
        $DB = $this->get_write_pdo();

        $filters = Utils::array_pick($this->attributes, self::$ATTRIBUTES);
        SQLHelper::insert($DB, 'j_channel', $filters);
        $this->id = $this->attributes['id'] = SQLHelper::$lastInsertId;
        return true;
    }

    public function update_channel(array $attr = null) {
        $attr = $this->validate($attr);
        if (!$attr) {
            return $this;
        }

        $DB = $this->get_write_pdo();
        $result = SQLHelper::update($DB, 'j_channel', $attr, $this->id, false);
        if ($result === false) {
            throw new Exception('修改失败。', 11);
        }
        $this->attributes = array_merge($this->attributes, $attr);
        return $this;
    }
}