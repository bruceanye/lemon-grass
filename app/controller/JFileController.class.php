<?php
/**
 * Created by PhpStorm.
 * User: chensheng
 * Date: 2017/9/17
 * Time: 上午11:53
 */

namespace diy\controller;


class JFileController extends BaseController {
    private function create_id() {
        return md5(uniqid());
    }

    public function upload() {
        $file = $_FILES['file'];
        if (!$file) {
            $this->exit_with_error(1, '无法获取文件，请检查服务器设置。', 400);
        }

        $id = $this->id = isset($_REQUEST['id']) && $_REQUEST['id'] != '' && $_REQUEST['id'] != 'undefined' ? $_REQUEST['id'] : $this->create_id();
        $type = isset($_REQUEST['name']) ? $_REQUEST['name'] : 'ad_url';
        $file_name = $file['name'];
        $md5 = $_REQUEST['md5'];


        $uploaddir = '/upload/';
        $uploadfile = $uploaddir . basename($file_name);

        if (move_uploaded_file($file['tmp_name'], $uploadfile)) {
            echo "File is valid, and was successfully uploaded.\n";
        } else {
            echo "Possible file upload attack!\n";
        }
        print_r($_FILES);
    }
}