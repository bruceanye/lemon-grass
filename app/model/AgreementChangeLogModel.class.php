<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 16/6/1
 * Time: 下午4:45
 */

namespace diy\model;


use diy\service\Admin;
use Exception;
use SQLHelper;

/**
 * @property int admin
 * @property int agreement_id
 * @property string ad_id
 */
class AgreementChangeLogModel extends Base {
  const TABLE = 't_ad_agreement_change_log';

  const CORRECT = 1;
  const ADD = 0;
  
  public function save( array $attr = null ) {
    $sql = "SELECT 'X'
            FROM `t_ad_agreement_change_log`
            WHERE `ad_id`=:ad_id AND `agreement_id`=:agreement_id AND `is_correct`=:type";
    $state = $this->get_read_pdo()->prepare($sql);
    $state->execute([
      ':ad_id' => $this->ad_id,
      ':agreement_id' => $this->agreement_id,
      ':type' => self::ADD,
    ]);
    if ($state->fetchColumn()) {
      throw new Exception('该合同已与本广告关联。', 100);
    }
    
    $attr = $this->validate($attr);
    $attr['admin'] = $_SESSION['id'];
    $attr['create_time'] = date('Y-m-d H:i:s');
    $this->attributes = array_merge($this->attributes, $attr);
    $DB = $this->get_write_pdo();
    SQLHelper::insert($DB, self::TABLE, $this->attributes);
    return $this;
  }

  public function toJSON(  ) {
    $json = parent::toJSON();
    $admin = new Admin();
    $json['admin'] = $admin->get_user_info(['id' => $this->admin])[$json['admin']];
    $agreement = new AgreementModel(['id' => $this->agreement_id]);
    $agreement->fetch();
    $json['new_company'] = $agreement->company;
    $json['new_agreement_id'] = $agreement->agreement_id;
    return $json;
  }

  protected function validate( array $attr = null ) {
    $attr = parent::validate($attr);
    if ($attr) {
      $attr['is_correct'] = (int)$attr['is_correct'];
    }
    return $attr;
  }

  public function remove() {
    $check = SQLHelper::delete($this->get_write_pdo(), self::TABLE, $this->attributes);
    return $check;
  }
}