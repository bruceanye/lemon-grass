<?php
/**
 * Created by PhpStorm.
 * User: chensheng
 * Date: 2017/9/16
 * Time: 下午5:28
 */

namespace diy\model;

use diy\utils\Utils;
use SQLHelper;


class JAgreementModel extends Base {
    static $ATTRIBUTES = array('full_name', 'aligns_name', 'number', 'start_date', 'end_date', 'comment',
        'address', 'type', 'telephone','contact_person','sign_person');

    public function save() {
        $DB = $this->get_write_pdo();

        $filters = Utils::array_pick($this->attributes, self::$ATTRIBUTES);
        SQLHelper::insert($DB, 'j_agreement', $filters);
        $this->id = $this->attributes['id'] = SQLHelper::$lastInsertId;
        return true;
    }
}