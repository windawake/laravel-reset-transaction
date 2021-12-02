## 快速预览
安装laravel5.5 - laravel8之间的版本，然后安装快速服务化的package
>composer require windawake/laravel-reset-transaction dev-master

首先创建ResetProductController.php控制器，创建ResetProductModel.php模型，创建reset_transaction和reset_product两张数据库表。这些操作只需要执行下面命令全部完成
```shell
php artisan resetTransact:create-examples  
```

phpunit.xml增加testsuite Transaction
```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="./vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true">
    <testsuites>
        ......

        <testsuite name="Transaction">
            <directory>./vendor/windawake/laravel-reset-transaction/tests</directory>
        </testsuite>
    </testsuites>
    ......
</phpunit>
```
然后启动web服务器
```shell
php artisan serve
```
最后运行测试命令 `./vendor/bin/phpunit --testsuite=Transaction`
运行结果如下所示，5个例子测试通过。
```shell
oot@DESKTOP-VQOELJ5:/web/linux/php/laravel/laravel62# ./vendor/bin/phpunit --testsuite=Transaction
PHPUnit 8.5.20 by Sebastian Bergmann and contributors.

.....                                                               5 / 5 (100%)

Time: 219 ms, Memory: 22.00 MB

OK (5 tests, 5 assertions)
```

## 功能特性
1. 开箱即用，不需要重构原有项目的代码，与mysql事务写法一致，简单易用。
2. 默认支持http协议的服务化接口，想要支持其它协议则需要重写中间件。
3. 高并发下，支持读已提交的事务隔离级别。
4. 由于把事务缩短，比mysql普通事务更少发生死锁。

## 原理解析
看过《明日边缘》电影就会知道，存档和读档的操作。这个分布式事务组件仿造《明日边缘》电影的原理，reset是重置的意思，即每次请求基础服务一开始时读档，然后继续后面的操作，结束时所有操作全部回滚并且存档，最后一步commit把存档全部执行成功。整个过程是遵守两段提交协议，先prepare，最后commit。

以用户A用招行卡转账100元给用户B招行账号的场景为例子，画了以下流程图。
![](https://cdn.learnku.com/uploads/images/202111/18/46914/RRw5OHCKvK.png!large)
右图开启reset分布式事务后，比左图多了请求4。请求4所做的事情，都是请求1-3之前做过的东西，又回来原点重新再来，最终提交事务，结束这转账的流程。

## 如何使用

以`vendor/windawake/laravel-reset-transaction/tests/TransactionTest.php`文件为例子
```php
<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class TransactionTest extends TestCase
{
    public function testCreateWithCommit()
    {
        $num = rand(1, 10000);
        $productName = 'php ' . $num;
        $data = [
            'store_id' => 1,
            'product_name' => $productName,
        ];
		// 开启分布式事务，其实是生成全局唯一id
        $transactId = $this->beginDistributedTransaction();
        $header = [
           在header 'transact_id' => $transactId,
        ];
		// 分布式事务内，请求都需要在request header带上transact_id
        $response = $this->post('api/resetProduct', $data, $header);
        $product = $response->json();
		// 分布式事务提交，也是接口请求，把之前的存档记录全部处理
        $this->commitDistributedTransaction($transactId);

        $response = $this->get('/api/resetProduct/' . $product['pid']);
        $product = $response->json();
        $this->assertEquals($productName, $product['product_name']);
    }

    private function beginDistributedTransaction()
    {
        return session_create_id();
    }

    private function commitDistributedTransaction($transactId)
    {
        $response = $this->post('/api/resetTransaction/commit', [], ['transact_id' => $transactId]);
        return $response->getStatusCode();
    }

    private function rollbackDistributedTransaction($transactId)
    {
        $response = $this->post('/api/resetTransaction/rollback', [], ['transact_id' => $transactId]);
        return $response->getStatusCode();
    }
}

```


## 个人笔记
我之前写了[laravel快速服务化包](https://learnku.com/articles/61638 "laravel快速服务化包")，但是它没有解决数据一致性的问题。尝试用XA，但是XA只能解决单机多个数据库，没法解决多台机器服务化的问题。然后我又尝试去研究tcc和seata，但是看完后一脸懵逼，不知所措。无奈被逼上绝路，没办法了只能自创分布式事务解决方案。一直以来，我一直以为单单只用mysql是没法解决分布式事务的问题，现在终于明白，还是有办法滴！