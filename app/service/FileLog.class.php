<?php

namespace diy\service;

use PDO;

class FileLog extends Base {
  public function insert($id, $type, $path, $file_name) {
    $DB = $this->get_write_pdo();
    $now = date('Y-m-d H:i:s');
    $upload_user = (int)$_SESSION['id'];
    $sql = "INSERT INTO `t_upload_log`
            (`id`, `TYPE`, `url`, `upload_user`, `upload_time`, `file_name`)
            VALUE (:id, :type, :path, '$upload_user', '$now', :file_name)";
    $state = $DB->prepare($sql);
    return $state->execute(array(
      ':id' => $id,
      ':type' => $type,
      ':path' => $path,
      ':file_name' => $file_name,
    ));
  }

  public function get_file_name($id) {
    $DB = $this->get_read_pdo();
    $sql = "SELECT `file_name`
            FROM `t_upload_log`
            WHERE `id`=:id AND `TYPE`='ad_url'
            ORDER BY `upload_time` DESC
            LIMIT 1";
    $state = $DB->prepare($sql);
    $state->execute(array(':id' => $id));
    return $state->fetchColumn();
  }

  public function insert_fetch_log( $id, $type, $path, $file, $final ) {
    $DB = $this->get_write_pdo();
    $now = date('Y-m-d H:i:s');
    $fetch_user = (int)$_SESSION['id'];
    $sql = "INSERT INTO `t_fetch_log`
            (`id`, `file_type`, `url`, `fetch_user`, `fetch_time`, `fetch_from`,
              `final_url`)
            VALUE (:id, :type, :url, $fetch_user, '$now', :from, :final)";
    $state = $DB->prepare($sql);
    return $state->execute(array(
      ':id' => $id,
      ':type' => $type,
      ':url' => $path,
      ':from' => $file,
      ':final' => $final,
    ));
  }

  public function select_upload_log( $ad_id ) {
    $DB = $this->get_read_pdo();
    $sql = "SELECT *
            FROM `t_upload_log`
            WHERE `id`=:id AND `TYPE`='ad_url'
            ORDER BY `upload_time` DESC";
    $state = $DB->prepare($sql);
    $state->execute(array(':id' => $ad_id));
    return $state->fetchAll(PDO::FETCH_ASSOC);
  }

  public function get_ad_history( $id ) {
    $DB = $this->get_read_pdo();
    $params = array(':id' => $id);
    $sql = "SELECT `url`, `upload_time` AS `time`, `NAME`, `file_name`
            FROM `t_upload_log` u LEFT JOIN `t_admin` a ON u.`upload_user`=a.`id`
            WHERE u.`id`=:id AND `TYPE`='ad_url'
            ORDER BY `upload_time` DESC";
    $state = $DB->prepare($sql);
    $state->execute($params);
    $upload = $state->fetchAll(PDO::FETCH_ASSOC);
    $sql = "SELECT `url`, `fetch_time` AS `time`, `NAME`, `fetch_from`
            FROM `t_fetch_log` f LEFT JOIN `t_admin` a ON f.`fetch_user`=a.`id`
            WHERE f.`id`=:id AND `file_type`='ad_url'
            ORDER BY `fetch_time` DESC";
    $state = $DB->prepare($sql);
    $state->execute($params);
    $fetch = $state->fetchAll(PDO::FETCH_ASSOC);
    $result = array_merge($upload, $fetch);
    usort($result, function ($a, $b) {
      return strcmp($b['time'], $a['time']);
    });
    return $result;
  }
}
