## 快速预览
安装laravel5.5 - laravel8之间的版本，然后安装composer包
```shell
## 必须使用composer2版本
composer require windawake/laravel-reset-transaction dev-master
```

首先创建order，storage，account3个mysql数据库实例，3个控制器，3个model，在phpunit.xml增加testsuite Transaction，然后启动web服务器。这些操作只需要执行下面命令全部完成
```shell
php artisan resetTransact:create-examples && php artisan serve --host=0.0.0.0 --port=8000
```
最后运行测试命令 `./vendor/bin/phpunit --testsuite=Transaction`
运行结果如下所示，3个例子测试通过。
```shell
oot@DESKTOP-VQOELJ5:/web/linux/php/laravel/laravel62# ./vendor/bin/phpunit --testsuite=Transaction
Time: 219 ms, Memory: 22.00 MB

OK (3 tests, 12 assertions)
```

## 功能特性
1. 开箱即用，不需要重构原有项目的代码，与mysql事务写法一致，简单易用。
2. 遵守两段提交协议，属于强一致性事务，高并发下，支持读已提交的事务隔离级别。
3. 由于事务拆分成多个，变成了几个小事务，压测发现比mysql普通事务更少发生死锁。
4. 支持事务嵌套，与savepoint一致效果。
5. 支持避免不同业务代码并发造成脏数据的问题。
6. 默认支持http协议的服务化接口，想要支持其它协议则需要重写中间件。
7. 支持子服务嵌套事务，子事务嵌套服务（正在完善中）。

## 解决了哪些并发场景
- [x] 一个待发货订单，用户同时操作发货和取消订单，只有一个成功
- [x] 积分换取优惠券，只要出现积分不够扣减或者优惠券的库存不够扣减，就会全部失败。

## 原理解析
Reset Transaction，中文名为重置型分布式事务，又命名为RT模式，与seata AT模式都是属于二段提交。
看过《明日边缘》电影就会知道，存档和读档的操作。这个分布式事务组件仿造《明日边缘》电影的原理，reset是重置的意思，即每次请求基础服务一开始时读档，然后继续后面的操作，结束时所有操作全部回滚并且存档，最后一步commit把存档全部执行成功。整个过程是遵守两段提交协议，先prepare，最后commit。

以用户A用招行卡转账100元给用户B招行账号的场景为例子，画了以下流程图。
![](https://cdn.learnku.com/uploads/images/202111/18/46914/RRw5OHCKvK.png!large)
右图开启reset分布式事务后，比左图多了请求4。请求4所做的事情，都是请求1-3之前做过的东西，又回来原点重新再来，最终提交事务，结束这转账的流程。

## 如何使用

以`vendor/windawake/laravel-reset-transaction/tests/ServiceTest.php`文件为例子
```php
<?php

namespace Tests\Transaction;

use App\Models\ResetAccountModel;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use App\Models\ResetOrderModel;
use App\Models\ResetStorageModel;
use GuzzleHttp\Client;
use Laravel\ResetTransaction\Facades\RT;

class ServiceTest extends TestCase
{
    private $baseUri = 'http://127.0.0.1:8000';
    /**
     * @var \GuzzleHttp\Client
     */
    private $client;

    protected function setUp(): void
    {
        parent::setUp();
        DB::setDefaultConnection('service_order');
        $this->client = new Client([
            'base_uri' => $this->baseUri,
            'timeout' => 60,
        ]);
    }

    public function testCreateOrderWithCommit()
    {
        $orderCount1 = ResetOrderModel::count();
        $storageItem1 = ResetStorageModel::find(1);
        $accountItem1 = ResetAccountModel::find(1);

        $transactId = RT::beginTransaction();
        $orderNo = rand(1000, 9999); // 随机订单号
        $stockQty = 2; // 占用2个库存数量
        $amount = 20.55; // 订单总金额20.55元

        ResetOrderModel::create([
            'order_no' => $orderNo,
            'stock_qty' => $stockQty,
            'amount' => $amount
        ]);
        // 请求库存服务，减库存
        $response = $this->client->put('/api/resetStorage/1', [
            'json' => [
                'decr_stock_qty' => $stockQty
            ],
            'headers' => [
                'transact_id' => $transactId,
                'transact_connection' => 'service_storage'
            ]
        ]);
        $resArr1 = $this->responseToArray($response);
        $this->assertTrue($resArr1['result'] == 1, 'lack of stock'); //返回值是1，说明操作成功
        // 请求账户服务，减金额
        $response = $this->client->put('/api/resetAccount/1', [
            'json' => [
                'decr_amount' => $amount
            ],
            'headers' => [
                'transact_id' => $transactId,
                'transact_connection' => 'service_account'
            ]
        ]);
        $resArr2 = $this->responseToArray($response);
        $this->assertTrue($resArr2['result'] == 1, 'not enough money'); //返回值是1，说明操作成功

        RT::commit();

        $orderCount2 = ResetOrderModel::count();
        $storageItem2 = ResetStorageModel::find(1);
        $accountItem2 = ResetAccountModel::find(1);

        $this->assertTrue(($orderCount1 + 1) == $orderCount2);
        $this->assertTrue(($storageItem1->stock_qty - $stockQty) == $storageItem2->stock_qty);
        $this->assertTrue(($accountItem1->amount - $amount) == $accountItem2->amount);
    }

    private function responseToArray($response)
    {
        $contents = $response->getBody()->getContents();
        return json_decode($contents, true);
    }
}


```


## 个人笔记
我之前写了[laravel快速服务化包](https://learnku.com/articles/61638 "laravel快速服务化包")，但是它没有解决数据一致性的问题。尝试用XA，但是XA只能解决单机多个数据库，没法解决多台机器服务化的问题。然后我又尝试去研究tcc和seata，但是看完后一脸懵逼，不知所措。无奈被逼上绝路，没办法了只能自创分布式事务解决方案。一直以来，我一直以为单单只用mysql是没法解决分布式事务的问题，现在终于明白，还是有办法滴！

<img src="https://cdn.learnku.com/uploads/images/202112/22/46914/os9rFuT9Sw.jpg" width="230px"/>

希望有更多的朋友相互学习和一起研究分布式事务的知识。
## 相关资源
https://learnku.com/articles/63797

laravel版本： https://github.com/windawake/laravel-reset-transaction

hyperf版本： https://github.com/windawake/hyperf-reset-transaction

