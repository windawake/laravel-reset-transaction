<?php

namespace Tests\Transaction;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use App\Models\ResetOrderModel;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;

class TransactionTest extends TestCase
{
    private $baseUri = 'http://127.0.0.1:8000';

    /**
     * Client
     *
     * @var \GuzzleHttp\Client
     */
    private $client;

    private $transactRollback = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new Client([
            'base_uri' => $this->baseUri,
            'timeout' => 60,
        ]);
    }

    public function testCreateWithoutTransact()
    {
        $num = rand(1, 10000);
        $orderName = 'php ' . $num;
        $data = [
            'store_id' => 1,
            'order_no' => $orderName,
        ];

        $response = $this->client->post('api/ResetOrder', [
            'json' => $data
        ]);
        $order = $this->responseToArray($response);

        $response = $this->client->get('/api/ResetOrder/' . $order['id']);
        $order = $this->responseToArray($response);

        $this->assertEquals($orderName, $order['order_no']);
    }

    public function testCreateWithoutCommit()
    {
        $num = rand(1, 10000);
        $orderName = 'php ' . $num;
        $data = [
            'store_id' => 1,
            'order_no' => $orderName,
        ];

        $transactId = $this->beginDistributedTransaction();
        $headers = [
            'transact_id' => $transactId,
        ];

        $response = $this->client->post('api/ResetOrder', [
            'json' => $data,
            'headers' => $headers
        ]);
        $this->responseToArray($response);

        $count = DB::table('reset_transaction')->where('transact_id', $transactId)->count();

        $this->assertEquals($count, 1);
    }

    public function testCreateWithCommit()
    {
        $num = rand(1, 10000);
        $orderName = 'php ' . $num;
        $data = [
            'store_id' => 1,
            'order_no' => $orderName,
        ];

        $transactId = $this->beginDistributedTransaction();
        $headers = [
            'transact_id' => $transactId,
        ];

        $response = $this->client->post('/api/ResetOrder', [
            'json' => $data,
            'headers' => $headers
        ]);
        $order = $this->responseToArray($response);

        $this->commitDistributedTransaction($transactId);

        $response = $this->client->get('/api/ResetOrder/' . $order['id']);
        $order = $this->responseToArray($response);
        $this->assertEquals($orderName, $order['order_no']);
    }

    public function testCreateWithRollback()
    {
        $num = rand(1, 10000);
        $orderName = 'php ' . $num;
        $data = [
            'store_id' => 1,
            'order_no' => $orderName,
        ];

        $transactId = $this->beginDistributedTransaction();
        $headers = [
            'transact_id' => $transactId,
        ];

        $response = $this->client->post('/api/ResetOrder', [
            'json' => $data,
            'headers' => $headers
        ]);
        $order = $this->responseToArray($response);

        $this->rollbackDistributedTransaction($transactId);

        $response = $this->client->get('/api/ResetOrder/' . $order['id']);
        $order = $this->responseToArray($response);
        $this->assertEquals($order, []);
    }

    public function testCommitTransact()
    {
        $item = DB::table('reset_transaction')->first();
        if ($item) {
            $transactId = $item->transact_id;
            $this->commitDistributedTransaction($transactId);
            $count = DB::table('reset_transaction')->where('transact_id', $transactId)->count();

            $this->assertEquals($count, 0);
        }
    }

    public function testUpdateWithCommit()
    {
        $data = [
            'order_no' => 'aaa',
        ];

        $transactId = $this->beginDistributedTransaction();
        $headers = [
            'transact_id' => $transactId,
        ];

        $response = $this->client->put('/api/ResetOrder/1', [
            'json' => $data,
            'headers' => $headers
        ]);
        $order = $this->responseToArray($response);

        $this->commitDistributedTransaction($transactId);
    }

    public function testForeachDeadlock1()
    {
        $this->initDeadlock();
        try {
            $this->createBench(2, function($i){
                if($i%2){
                    $this->createDeadlock2();
                } else {
                    $this->createDeadlock1();
                }
            });
            $this->assertNull(null);
        } catch(\Exception $ex){
            $this->assertNull(1, $ex->getMessage());
        }

    }

    public function testForeachDeadlock2()
    {
        $this->initDeadlock();
        try {
            $this->createBench(2, function($i){
                if($i%2){
                    $this->createDeadlock4();
                } else {
                    $this->createDeadlock3();
                }
            });
            $this->assertNull(null);
        } catch(\Exception $ex){
            $this->assertNull(1, $ex->getMessage());
        }
    }

    private function initDeadlock()
    {
        ResetOrderModel::updateOrCreate(['id' => 1], ['order_no' => rand(100, 999)]);
        ResetOrderModel::updateOrCreate(['id' => 2], ['order_no' => rand(100, 999)]);
    }

    private function createDeadlock1()
    {
        $transactId = $this->beginDistributedTransaction();
        $headers = [
            'transact_id' => $transactId,
        ];

        $this->client->put('api/ResetOrder/1', [
            'json' => ['order_no' => rand(100, 999)],
            'headers' => $headers,
        ]);

        $this->client->put('api/ResetOrder/2', [
            'json' => ['order_no' => rand(100, 999)],
            'headers' => $headers,
        ]);

        $this->client->put('api/ResetOrder/1', [
            'json' => ['order_no' => rand(100, 999)],
            'headers' => $headers,
        ]);

        $this->commitDistributedTransaction($transactId);
    }

    private function createDeadlock2()
    {
        $transactId = $this->beginDistributedTransaction();
        $headers = [
            'transact_id' => $transactId,
        ];

        $this->client->put('api/ResetOrder/2', [
            'json' => ['order_no' => rand(100, 999)],
            'headers' => $headers,
        ]);

        $this->client->put('api/ResetOrder/1', [
            'json' => ['order_no' => rand(100, 999)],
            'headers' => $headers,
        ]);

        $this->commitDistributedTransaction($transactId);
    }

    private function createDeadlock3()
    {
        DB::beginTransaction();

        ResetOrderModel::where('id', 1)->update(['order_no' => rand(100, 999)]);
        ResetOrderModel::where('id', 2)->update(['order_no' => rand(100, 999)]);
        ResetOrderModel::where('id', 1)->update(['order_no' => rand(100, 999)]);

        DB::commit();
    }

    private function createDeadlock4()
    {
        DB::beginTransaction();

        ResetOrderModel::where('id', 2)->update(['order_no' => rand(100, 999)]);
        ResetOrderModel::where('id', 1)->update(['order_no' => rand(100, 999)]);

        DB::commit();
    }

    private function createBench($count, $callback)
    {
        // 进程数量
        $ids = [];
        for ($i = 0; $i < $count; ++$i) {
            $id = pcntl_fork();
            if ($id < 0) {
                // 主进程
                throw new \Exception('创建子进程失败: ' . $i);
            } else if ($id > 0) {
                // 主进程
                $ids[] = $id;
            } else {
                // 子进程
                try {
                    $callback($i);
                } catch (\Exception $ex) {
                    throw $ex;
                }
                // 退出子进程
                exit;
            }
        }
    }

    public function testNestedTransaction()
    {
        // DB::beginTransaction();
        // ResetOrderModel::where('id', 1)->update(['order_no' => 'aaa']);
        //     DB::beginTransaction();
        //     ResetOrderModel::where('id', 2)->update(['order_no' => 'bbb']);
        //         DB::beginTransaction();
        //         ResetOrderModel::where('id', 3)->update(['order_no' => 'ccc']);
        //         DB::commit();
        //     DB::rollBack();
        // DB::commit();

        $txId = $this->beginDistributedTransaction();
        $this->client->put('api/ResetOrder/1', [
            'headers' => ['transact_id' => $txId],
            'json' => ['order_no' => 'aaa']
        ]);
            $txId2 = $this->beginDistributedTransaction();
            $txId2 = implode('-', [$txId, $txId2]);
            $this->client->put('api/ResetOrder/2', [
                'headers' => ['transact_id' => $txId2],
                'json' => ['order_no' => 'bbb']
            ]);

                $txId3 = $this->beginDistributedTransaction();
                $txId3 = implode('-', [$txId2, $txId3]);
                $this->client->put('api/ResetOrder/3', [
                    'headers' => ['transact_id' => $txId3],
                    'json' => ['order_no' => 'ccc']
                ]);

                $this->commitDistributedTransaction($txId3);

            $this->rollbackDistributedTransaction($txId2);

        $this->commitDistributedTransaction($txId);
        $this->transactRollback = [];
    }


    private function beginDistributedTransaction()
    {
        return session_create_id();
    }

    private function commitDistributedTransaction($transactId)
    {
        $txIdArr = explode('-', $transactId);
        if (count($txIdArr) > 1) {
            return true;
        }

        $response = $this->client->post('/api/resetTransaction/commit', [
            'headers' => [
                'transact_id' => $transactId,
                'transact_rollback' => $this->transactRollback ? json_encode($this->transactRollback) : '',
                'transact_check' => 1,
            ]
        ]);
        return $response->getStatusCode();
    }

    private function rollbackDistributedTransaction($transactId)
    {
        $txIdArr = explode('-', $transactId);
        if (count($txIdArr) > 1) {
            $transactId = str_replace('-', '.', $transactId);
            Arr::set($this->transactRollback, $transactId, 1);
            return true;
        }

        $response = $this->client->post('/api/resetTransaction/rollback', [
            'headers' => ['transact_id' => $transactId]
        ]);
        return $response->getStatusCode();
    }

    private function responseToArray($response)
    {
        $contents = $response->getBody()->getContents();
        return json_decode($contents, true);
    }
}
