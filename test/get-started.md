接口测试 - 开始测试
========

1. 复制 `tool/set_session.php` 到 `public/`。
2. 创建测试 `MyTest.php`，继承 `\Meathill\Friday`
3. 创建接口描述文档 `my-test.json`，定义接口、输入输出数据类型
4. 将测试用例添加到 `phpunit.api.xml`
5. 执行 `phpunit -c phpunit.api.xml` 进行测试

## `set_session.php`

后台项目使用 [`sessions`](http://php.net/manual/zh/book.session.php) 处理用户登录，所以测试时需要访问这个文件获取用户权限。

> 注意不要把这个文件放在 `public/` 目录里提交！

> 注意不要把这个文件放在 `public/` 目录里提交！

> 注意不要把这个文件放在 `public/` 目录里提交！

这个文件可以接受参数取代默认设置，这样我们在测试时可以模拟不同的用户进行不同操作。常见参数如下：

| 参数 | 含义 | 
| -------- | -------- |
| role | 后台用户类别，如管理员、运维 |
| id | 后台用户id |
| user | 用户名 |

## `MyTest`

`MyTest` 应该继承 `Friday`，并声明接口描述的路径和本测试的名称（用于输出日志）。最简单的范例如下：

```php
class MyTest extends Friday {
  protected $configJSON = PROJECT_DIR . 'test/api/diy/diy.json'; // 接口描述文件的绝对路径。
  protected $name = 'my'; // 用来输出日志，方便定位问题
  protected $session = [
    'id' => '123456',
  ]; // 定义需要设置的用户信息
  
  public function testAll() {
    $this->doTest(); // 启动测试
  }
}
```

需要注意的是，默认条件下，PHPUnit 会启动所有以 `test` 作为开头的方法。每个测试都会启动 `setUp()` 方法创建新的虚拟客户端，每个 `doTest()` 方法都会把描述文件里所有的接口进行测试，所以除非有特殊需求，不然只要写一个 `testAll()` 方法即可。

## `phpunit.api.xml` 与 `bootstrap.api.php`

前者是包含所有测试的 PHPUnit 配置文件，后者是启动文件，里面声明了一些测试中可能用到的常量。

| 常量 | 内容 |
| -------- | -------- |
| PROJECT_DIR | 项目目录的绝对路径，用于各处加载 |
| API_URL | API 地址，用来进行测试 | 