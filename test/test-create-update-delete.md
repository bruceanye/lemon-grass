接口测试 - 创建、修改、删除
========

CRUD 都是我们测试的必备内容。`Friday` 为应对这些需求，提供 `register()` 方法，以注册回调的方式处理接口返回值，实现创建、修改、删除的测试。

## `register()`

### 说明

```
void register(string $method, string $api, callable $callback)
```

注册一个回调，使用 `method` 方法访问 `api` 并执行完接口描述规定的测试之后调用 `callback`。

### 参数

<dl>
  <dt>`method`</dt>
  <dd>方法</dd>
  <dt>`api`</dt>
  <dd>接口</dd>
  <dt>`callback`</dt>
  <dd>回调函数，需在类内定义</dd>
</dl>

## 范例

**SomeTest.php**
```php
class SomeTest extends Friday {
  protected $configJSON = PROJECT_DIR  . 'test/api/some.json';
  protected $name = 'some';
  protected $session = [
    'id' => 12,
  ];

  // 创建一个私有变量，用来存储创建后的id
  protected $id;

  public function testAll() {
    $this->doTest();
  }

  protected function setUp() {
    parent::setUp();

    $this->register('POST', 'some/', 'onPOST_some');
  }

  protected function validateAPI($test, $api) {
    $api = str_replace('{{id}}', $this->id, $api);
    parent::validateAPI($test, $api);
  }


  protected function onPOST_some($response) {
    // 这里的 `$response` 是已经转义后的，如果是 JSON，那么就会 `json_decode` 成数组
    $this->id = $response['data']['id'];
  }
}
```

**some.json**
```json
{
  "some/": [
    {
      "method": "GET"
      .... // 这个接口就不写细节了
    },
    {
      "method": "POST",
      "comment": "创建",
      "input": {
        "json": {
          "name": "肉山",
          "sex": "男",
          "age": 32
        }
      },
      "response": {
        "statusCode": 201
      },
      "output": {
        "code": 0,
        "msg": "string",
        "data": {
          "type": "object",
          "fields": {
            "id": "int"
          }
        }
      }
    }
  ],
  "some/{{id}}": [
    {
      "method": "PATCH",
      "input": {
        "json": {
          "age": 33
        }
      },
      "output": {
        "code": 0,
        "data": {
          "type": "object",
          "fields": {
            "age": 33
          }
        }
      }
    },
    {
      "method": "DELETE",
      "output": {
        "code": 0
      }
    }
  ]
}
```