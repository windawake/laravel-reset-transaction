# laravel-reset-transaction
[![中文](https://shields.io/static/v1?label=zh-cn&message=%E4%B8%AD%E6%96%87&color=red)](https://github.com/windawake/laravel-reset-transaction/blob/master/README_zh-CN.md)

distributed transaction for call remote api service

![](https://github.com/windawake/notepad/blob/master/images/webchat01.jpg)

In order to discuss technology with me, you can add me to wechat.

## Overview
Install the version between laravel5.5-laravel8, and then install the package
>composer require windawake/laravel-reset-transaction dev-master

First create the ResetProductController.php controller, create the ResetProductModel.php model, create two database tables reset_transaction and reset_product. These operations only need to execute the following commands to complete all
```shell
php artisan resetTransact:create-examples  
```

add testsuite Transaction in phpunit.xml 
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
then start web server
```shell
php artisan serve
```
Finally, run the test command `./vendor/bin/phpunit --testsuite=Transaction`
The running results are as follows, and five examples pass the test.
```shell
oot@DESKTOP-VQOELJ5:/web/linux/php/laravel/laravel62# ./vendor/bin/phpunit --testsuite=Transaction
PHPUnit 8.5.20 by Sebastian Bergmann and contributors.

.....                                                               5 / 5 (100%)

Time: 219 ms, Memory: 22.00 MB

OK (5 tests, 5 assertions)
```

## Feature
1. Use it out of the box, no need to refactor the code of the original project, consistent with the mysql transaction writing, simple and easy to use.
2. The service interface of the http protocol is supported by default. If you want to support other protocols, you need to rewrite the middleware.
3. Under high concurrency, the isolation level of read committed transactions is supported.
4. As the transaction is shortened, deadlocks occur less frequently than mysql ordinary transactions.

## Principle
You will know the operation of archiving and reading files after watching the movie "Edge of Tomorrow". This distributed transaction component imitates the principle of the "Edge of Tomorrow" movie. Reset means to reset, that is, read the file at the beginning of each request for the basic service, and then continue the subsequent operations. At the end, all operations are rolled back and archived, and finally One step commit successfully executes all the archives. The whole process is to comply with the two-stage submission agreement, first prepare, and finally commit.

Taking the scenario where user A transfers 100 yuan to user B's China Merchants Bank account with a China Merchants Bank card as an example, the following flowchart is drawn. ![](https://cdn.learnku.com/uploads/images/202111/18/46914/RRw5OHCKvK.png!large)
After the reset distributed transaction is turned on in the right picture, there are 4 more requests than the left picture. What request 4 does is what was done before request 1-3, and then come back to the original point and start again, finally submit the transaction, and end the transfer process.

## How to use

Take the `vendor/windawake/laravel-reset-transaction/tests/TransactionTest.php` file as an example
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
		// Start distributed transactions is actually generating a globally unique id
        $transactId = $this->beginDistributedTransaction();
        $header = [
           在header 'transact_id' => $transactId,
        ];
		// In a distributed transaction, all requests need to carry transact_id in the request header
        $response = $this->post('api/resetProduct', $data, $header);
        $product = $response->json();
		// Distributed transaction submission is also an interface request to process all the previous archive records
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
