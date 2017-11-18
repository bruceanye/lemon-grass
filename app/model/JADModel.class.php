<?php
/**
 * Created by PhpStorm.
 * User: chensheng
 * Date: 2017/9/24
 * Time: 下午2:55
 */

namespace diy\model;

use diy\utils\Utils;
use SQLHelper;
use Exception;

class JADModel extends Base {
    static $ATTRIBUTES_TYPE1 = array('type', 'name', 'cooperation_type', 'market_type', 'money', 'rate', 'link', 'agreement_id',
         'online_time', 'offline_time', 'owner', 'execute_owner','comment');
    static $ATTRIBUTES_TYPE2 = array('type', 'name', 'cooperation_type', 'money', 'link', 'agreement_id',
         'online_time', 'offline_time', 'owner', 'execute_owner','comment');


    public function save() {
        $DB = $this->get_write_pdo();

        $attr = $this->attributes;
        $market_attr = [];

        // 过滤market_type类型
        if ($attr['market_type']) {
            $market_attr['market_type']  = $attr['market_type'];
            unset($attr['market_type']);
        }

        if ($attr['type'] == 1) {
            $attr['cooperation_type'] = $attr['cooperation_type'][0];
            $filters = Utils::array_pick($attr, self::$ATTRIBUTES_TYPE1);
        } else {
            $attr['cooperation_type'] = $attr['cooperation_type'][1];
            $filters = Utils::array_pick($attr, self::$ATTRIBUTES_TYPE2);
        }
        $filters['money'] = $filters['money'] * 100;
        SQLHelper::insert($DB, 'j_client_ad', $filters);
        $this->id = $this->attributes['id'] = SQLHelper::$lastInsertId;

        // 插入j_ad_market
        if ($market_attr['market_type']) {
            foreach($market_attr['market_type'] as $key => $value) {
                $item = array(
                    'ad_id' => $this->id,
                    'type' => $value,
                    'price' => $attr['money'] * 100,
                    'link' => $attr['link']
                );
                SQLHelper::insert($DB, 'j_ad_market', $item);
            };
        }
        return true;
    }

    public function update_ad(array $attr = null) {
        $attr = $this->validate($attr);
        if (!$attr) {
            return $this;
        }

        $DB = $this->get_write_pdo();
        $result = SQLHelper::update($DB, 'j_client_ad', $attr, $this->id, false);
        if ($result === false) {
            throw new Exception('修改失败。', 11);
        }
        $this->attributes = array_merge($this->attributes, $attr);
        return $this;
    }

    public function update_market(array $attr = null) {
        $DB = $this->get_write_pdo();
        $result = SQLHelper::update($DB, 'j_ad_market', $attr, $this->id, false);
        if ($result === false) {
            throw new Exception('修改失败。', 11);
        }
        $this->attributes = array_merge($this->attributes, $attr);
        return $this;
    }
}