接口测试 - 结合数据库测试
========

有些时候我们访问接口之后，除了验证接口返回值，还有一些其它的操作需要检查。同样，这里也可以通过 [`register()`](./test-create-update-delete.md) 方法注册回调来完成。

`Friday` 本身不提供建立数据连接的方式，因为考虑到将来可能会把它迁移出去作为一个独立的库。所以需要手工连接。这里可以借由 `app/connector/` 里的文件来实现。

## 适用范围

1. 每次测试完成后，将数据库中的数据修改回初始状态
2. 检查测试后其它操作是否正确

注意，需要确保读写的库一致。

## 范例

**SomeTest.php**

```php
class SomeTest extends Friday {
  // 重复的就不写了
  protected function setUp() {
    parent::setUp();

    $this->register('POST', 'some/', 'onPOST_some');
  }

  protected function onPOST_some($response) {
    $DB = require PROJECT_DIR . '/app/connector/pdo.php';
    $sql = "SELECT *
            FROM `t_some_table`
            WHERE `id`=:id";
    $state = $DB->prepare($sql);
    $state->execute([
      ':id' => $response['data']['id'];
    ]);
    $user = $state->fetch(PDO::FETCH_ASSOC);
    $this->assetEqual($user['name'], '肉山');
  }
}
```