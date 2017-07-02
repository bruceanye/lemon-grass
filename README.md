Lemon Grass
===========

Lemon Grass 是我们后台的主要框架，其中也包含了自助后台的功能实现。

## 系统要求

* PHP >= 5.6
* PHP APCu
* PHP Redis
* PHP Mcrypt （微信）
* PHP XML （PHPExcel/Mustache）
* composer

## 安装

1. 取得源代码，有两种方式，任选一即可：
    1. `git clone https://github.com/Dianjoy/lemon-grass`
    2. `composer create-project dianjoy/lemon-grass ./lemon-grass --keep-vcs`
2. `composer install`
3. 修改 `config/config.php`，常量说明见下表
4. 修改 `.htaccess` 或设置 nginx

| 常量      | 释意      | 建议值    |
| -------- | -------- | -------- |
| DEBUG    | 是否处于调试状态 | false |
| BASE     | 服务路径起始点，需要配合 .htaccess 调整 | 空字符 |
| UPLOAD_BASE | 上传文件后保存路径，相对于项目路径 | 默认在 ./public/upload |
| UPLOAD_URL | 上传文件后，通过浏览器访问时的起始路径 | |
| LOCAL_FILE | 用于鉴别要抓取的文件是否位于本地的正则 | |
| ALLOW_ORIGIN | 允许从哪个域名访问 | 前端部署服务器地址 |

## 测试

本项目使用 [PHPUnit](https://phpunit.de/) 进行单元测试。

测试分两块：

1. 作为框架部分的单元测试，对应 [phpunit.xml](./phpunit.xml)
2. 对接口做测试，对应 [phpunit.api.xml](./phpunit.api.xml)

具体测试相关的文档，请参阅 [测试介绍](./test/README.md)

## 感谢

本项目从众多开源项目当中获益，有些是代码，有些是思路。这里不一一列举，谨向所有共享自己代码的人致意。

## 授权

[GPL](https://www.gnu.org/licenses/gpl-3.0.html)

--------
> 柠檬草配虎虾，啧啧，感觉闻到海的味道了……好像躺在沙滩上晒太阳…… [Tiger Prawn](https://github.com/Dianjoy/tiger-prawn)
