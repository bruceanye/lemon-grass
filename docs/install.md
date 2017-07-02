部署方法
========

## 依赖

* PHP >=5.6
* PHP Redis
* PHP mcrypt
* PHP XML
* [Composer](https://getcomposer.org/)
* [Node.js](https://nodejs.org/) >= 4.x
* [Grunt](https://gruntjs.com/) >= 1.0
* [bower](https://bower.io/)
* [Ruby](https://ruby-lang.org/) >= 2.2
* [Compass](https://compass-style.org/) >= 1.0
* Git
* [Apktool](https://ibotpeaches.github.io/Apktool/) 解 apk 包用
* aapt `{Android SDK}/build-tools/{版本号}/aapt` ，用来解析 apk 包

## 初次部署

1. clone 代码到本地
    ```bash
    git clone git@git.yxpopo.com:dianjoy/lemon-grass.git
    ```
2. 安装依赖
    ```bash
    composer install --no-dev
    ```
3. 设置重定向
    1. .htaccss
    2. Nginx
4. 修改数据库配置，详见 [数据库连接类文档](http://git.yxpopo.com/tech/docs/blob/master/admins/db.md#api)
5. 复制 config/config.php.sample 为 config/config.php，并修改其配置，详见 [config.php 常量说明表](#table)
6. 部署邮件模板
    ```bash
    git submodule init
    git submodule update
    cd template/email/
    npm install
    bower install
    grunt
    ```
7. 完成！

## 更新

1. 更新代码
    ```bash
    git pull
    git submodule update
    ```
2. 更新依赖
    ```bash
    composer install --no-dev
    ```
3. 更新邮件模板
    ```bash
    cd template/email/
    npm install
    bower install
    grunt
    ```
4. 如有需要，修改 config.php
4. 完成

--------

## 附表

<a name="table1"></a>### config.php 常量说明表

| 常量      | 释意      | 建议值    |
| -------- | -------- | -------- |
| DEBUG    | 是否处于调试状态 | false |
| BASE     | 服务路径起始点，需要配合 .htaccess 调整 | 空字符 |
| UPLOAD_BASE | 上传文件后保存路径，相对于项目路径 | 默认在 ./public/upload |
| UPLOAD_URL | 上传文件后，通过浏览器访问时的起始路径 | |
| LOCAL_FILE | 用于鉴别要抓取的文件是否位于本地的正则 | |
| SALT | 登录时用来混淆密码的盐 | 无规则字符串 |
| BAOBEI_PASSWORD | 收取报备邮件邮箱的密码啊 | |
| TEMP | 临时文件存放处 | `/tmp` |
| PROJECT_NAME | 项目名称，用来作为日志等内容里的标识 | `diy` |
| OP_MAIL | 运营的公用邮箱。后台可能会发送邮件给运营 | op@dianjoy.com |
| MAIL_PASSWORD | support@dianjoy.com 的登录密码 | |
| TAX_RATIO | 税点 | `1` |
| ALLOW_ORIGIN | 允许从哪个域名访问 | 前端部署服务器地址，比如 http://diy.dianjoy.com/ |
| INNER_SERVICE | inner_service 地址，用来读取广告平台数据 | |