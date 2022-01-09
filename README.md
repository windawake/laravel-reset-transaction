# laravel-reset-transaction
[![中文文档](https://shields.io/static/v1?label=zh-cn&message=%E4%B8%AD%E6%96%87&color=red)](https://github.com/windawake/laravel-reset-transaction/blob/master/README_zh-CN.md)

distributed transaction for call remote api service

## Overview
Install the version between laravel5.5-laravel8, and then install the composer package
```shell
## Composer2 version must be used
composer require windawake/laravel-reset-transaction dev-master
```

First create order, storage, account 3 mysql database instances, 3 controllers, 3 models, add testsuite Transaction to phpunit.xml, and then start the web server. These operations only need to execute the following commands to complete all
```shell
php artisan resetTransact:create-examples && php artisan serve --host=0.0.0.0 --port=8000
```
Open another terminal and start the web server with port 8001
```shell
php artisan serve --host=0.0.0.0 --port=8001
```
Finally run the test script `
./vendor/bin/phpunit --testsuite=Transaction --filter=ServiceTest
`The running results are shown below, and the 3 examples have passed the test.
```shell
DESKTOP:/web/linux/php/laravel/laravel62# ./vendor/bin/phpunit --testsuite=Transaction --filter=ServiceTest
Time: 219 ms, Memory: 22.00 MB

OK (3 tests, 12 assertions)
```

## Feature
1. Out of the box, no need to refactor the code of the original project, consistent with the mysql transaction writing, simple and easy to use.
2. Comply with the two-stage commit protocol, which is a strong consistency transaction. Under high concurrency, it supports the isolation level of read committed transactions, and the data consistency is 100% close to mysql xa.
3. Since the transaction is split into multiple and become several small transactions, the stress test finds that there are fewer deadlocks than mysql ordinary transactions.
4. Support distributed transaction nesting, consistent with savepoint.
5. Support to avoid the problem of dirty data caused by the concurrency of different business codes.
6. The service-oriented interface of the http protocol is supported by default. If you want to support other protocols, you need to rewrite the middleware.
7. <a href="#support-sub-service-nested-distributed-transaction-worlds-first">Support for nested distributed transactions of sub-services (world's first)</a>.
8. Support services, mixed nesting of local transactions and distributed transactions (the world's first)
9. Support 3 retries over time, repeated requests to ensure idempotence
10. Support go, java language (under development)

## Principle
You will know the operation of archiving and reading files after watching the movie "Edge of Tomorrow". This distributed transaction component imitates the principle of the "Edge of Tomorrow" movie. Reset means to reset, that is, read the file at the beginning of each request for the basic service, and then continue the subsequent operations. At the end, all operations are rolled back and archived, and finally One step commit successfully executes all the archives. The whole process is to comply with the two-stage submission agreement, first prepare, and finally commit.

Taking the scenario where user A transfers 100 yuan to user B's China Merchants Bank account with a China Merchants Bank card as an example, the following flowchart is drawn. ![](https://cdn.learnku.com/uploads/images/202111/18/46914/RRw5OHCKvK.png!large)
After the reset distributed transaction is turned on in the right picture, there are 4 more requests than the left picture. What request 4 does is what was done before request 1-3, and then come back to the original point and start again, finally submit the transaction, and end the transfer process.

## Support sub-service nested distributed transaction (world's first)
![](https://cdn.learnku.com/uploads/images/202112/30/46914/IzHhjfjHC1.png!large)
A world-class problem: A service commit->B service rollback->C service commit->D service commit sql. In this scenario, ABCD are all different databases. How can I make A service submit B service and roll back? What about all operations of the C service and D service?

Neither seata nor go-dtm can solve this problem. The key point to solve the problem is that the C service and D service must be submitted falsely, and cannot be submitted truly. If they are submitted, they will be unable to recover.

What are the benefits of implementing nested distributed transactions that support sub-services? You can make A service a service of others, and it can be nested in any layer of the link arbitrarily. Breaking the previous shackles: Service A must be a root service, and if Service A is to become a sub-service, the code must be changed. If the RT mode is used, service A can become someone else's service without modifying the code.

## How to use

Take the `vendor/windawake/laravel-reset-transaction/tests/ServiceTest.php` file as an example
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
        DB::setDefaultConnection('service_order'); //默认是订单服务
        $this->client = new Client([
            'base_uri' => $this->baseUri,
            'timeout' => 60,
        ]);
		$requestId = session_create_id();
		session()->put('rt_request_id', $requestId);
    }

    public function testCreateOrderWithCommit()
    {
        $orderCount1 = ResetOrderModel::count();
        $storageItem1 = ResetStorageModel::find(1);
        $accountItem1 = ResetAccountModel::find(1);
		// 开启RT模式分布式事务
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
		$requestId = session_create_id();
        $response = $this->client->put('/api/resetStorage/1', [
            'json' => [
                'decr_stock_qty' => $stockQty
            ],
            'headers' => [
                'rt_request_id' => $requestId,
                'rt_transact_id' => $transactId,
                'rt_connection' => 'service_storage'
            ]
        ]);
        $resArr1 = $this->responseToArray($response);
        $this->assertTrue($resArr1['result'] == 1, 'lack of stock'); //返回值是1，说明操作成功
        // 请求账户服务，减金额
		$requestId = session_create_id();
        $response = $this->client->put('/api/resetAccount/1', [
            'json' => [
                'decr_amount' => $amount
            ],
            'headers' => [
                'rt_request_id' => $requestId,
                'rt_transact_id' => $transactId,
                'rt_connection' => 'service_account'
            ]
        ]);
        $resArr2 = $this->responseToArray($response);
        $this->assertTrue($resArr2['result'] == 1, 'not enough money'); //返回值是1，说明操作成功
		// 提交RT模式分布式事务
        RT::commit();

        $orderCount2 = ResetOrderModel::count();
        $storageItem2 = ResetStorageModel::find(1);
        $accountItem2 = ResetAccountModel::find(1);

        $this->assertTrue(($orderCount1 + 1) == $orderCount2); //事务内创建了一个订单
        $this->assertTrue(($storageItem1->stock_qty - $stockQty) == $storageItem2->stock_qty); //事务内创建订单后需要扣减库存
        $this->assertTrue(($accountItem1->amount - $amount) == $accountItem2->amount); //事务内创建订单后需要扣减账户金额
    }

    private function responseToArray($response)
    {
        $contents = $response->getBody()->getContents();
        return json_decode($contents, true);
    }
}

```

## Contact


![](https://cdn.learnku.com/uploads/images/202201/06/46914/7bFo5E0okb.jpg!large)

I hope that more friends will learn from each other and study the knowledge of distributed transactions together.
