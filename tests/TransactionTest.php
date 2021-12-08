<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use App\Models\ResetProductModel;
use GuzzleHttp\Client;

class TransactionTest extends TestCase
{
    private $baseUri = 'http://127.0.0.1:8000';

    /**
     * Client
     *
     * @var \GuzzleHttp\Client
     */
    private $client;

    protected function setUp() : void
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
        $productName = 'php ' . $num;
        $data = [
            'store_id' => 1,
            'product_name' => $productName,
        ];

        $response = $this->client->post('api/resetProduct', [
            'json' => $data
        ]);
        $product = $this->responseToArray($response);

        $response = $this->client->get('/api/resetProduct/' . $product['pid']);
        $product = $this->responseToArray($response);
        
        $this->assertEquals($productName, $product['product_name']);
    }

    public function testCreateWithoutCommit()
    {
        $num = rand(1, 10000);
        $productName = 'php ' . $num;
        $data = [
            'store_id' => 1,
            'product_name' => $productName,
        ];

        $transactId = $this->beginDistributedTransaction();
        $header = [
            'transact_id' => $transactId,
        ];

        $response = $this->client->post('api/resetProduct', [
            'json' => $data,
            'headers' => $header
        ]);
        $this->responseToArray($response);

        $count = DB::table('reset_transaction')->where('transact_id', $transactId)->count();

        $this->assertEquals($count, 1);
    }

    public function testCreateWithCommit()
    {
        $num = rand(1, 10000);
        $productName = 'php ' . $num;
        $data = [
            'store_id' => 1,
            'product_name' => $productName,
        ];

        $transactId = $this->beginDistributedTransaction();
        $header = [
            'transact_id' => $transactId,
        ];

        $response = $this->client->post('/api/resetProduct', [
            'json' => $data,
            'headers' => $header
        ]);
        $product = $this->responseToArray($response);

        $this->commitDistributedTransaction($transactId);

        $response = $this->client->get('/api/resetProduct/' . $product['pid']);
        $product = $this->responseToArray($response);
        $this->assertEquals($productName, $product['product_name']);
    }

    public function testCreateWithRollback()
    {
        $num = rand(1, 10000);
        $productName = 'php ' . $num;
        $data = [
            'store_id' => 1,
            'product_name' => $productName,
        ];

        $transactId = $this->beginDistributedTransaction();
        $header = [
            'transact_id' => $transactId,
        ];

        $response = $this->client->post('/api/resetProduct', [
            'json' => $data,
            'headers' => $header
        ]);
        $product = $this->responseToArray($response);

        $this->rollbackDistributedTransaction($transactId);

        $response = $this->client->get('/api/resetProduct/' . $product['pid']);
        $product = $this->responseToArray($response);
        $this->assertEquals($product, []);
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

    // public function testForeachDeadlock1()
    // {
    //     $this->initDeadlock();
    //     try {
    //         $this->createBench(10, function($i){
    //             if($i%2){
    //                 $this->createDeadlock2();
    //             } else {
    //                 $this->createDeadlock1();
    //             }
    //         });
    //         $this->assertNull(null);
    //     } catch(\Exception $ex){
    //         $this->assertNull(1, $ex->getMessage());
    //     }
        
    // }

    // public function testForeachDeadlock2()
    // {
    //     $this->initDeadlock();
    //     try {
    //         $this->createBench(10, function($i){
    //             if($i%2){
    //                 $this->createDeadlock4();
    //             } else {
    //                 $this->createDeadlock3();
    //             }
    //         });
    //         $this->assertNull(null);
    //     } catch(\Exception $ex){
    //         $this->assertNull(1, $ex->getMessage());
    //     }
    // }

    private function initDeadlock()
    {
        ResetProductModel::updateOrCreate(['pid' => 1], ['product_name' => rand(100, 999)]);
        ResetProductModel::updateOrCreate(['pid' => 2], ['product_name' => rand(100, 999)]);
    }

    private function createDeadlock1()
    {
        $transactId = $this->beginDistributedTransaction();
        $header = [
            'transact_id' => $transactId,
        ];

        $this->client->put('api/resetProduct/1', [
            'json' => ['product_name' => rand(100, 999)],
            'headers' => $header,
        ]);

        $this->client->put('api/resetProduct/2', [
            'json' => ['product_name' => rand(100, 999)],
            'headers' => $header,
        ]);

        $this->client->put('api/resetProduct/1', [
            'json' => ['product_name' => rand(100, 999)],
            'headers' => $header,
        ]);

        $this->commitDistributedTransaction($transactId);

    }

    private function createDeadlock2()
    {
        $transactId = $this->beginDistributedTransaction();
        $header = [
            'transact_id' => $transactId,
        ];

        $this->client->put('api/resetProduct/2', [
            'json' => ['product_name' => rand(100, 999)],
            'headers' => $header,
        ]);

        $this->client->put('api/resetProduct/1', [
            'json' => ['product_name' => rand(100, 999)],
            'headers' => $header,
        ]);

        $this->commitDistributedTransaction($transactId);
    }

    private function createDeadlock3()
    {
        DB::beginTransaction();

        ResetProductModel::where('pid', 1)->update(['product_name' => rand(100, 999)]);
        ResetProductModel::where('pid', 2)->update(['product_name' => rand(100, 999)]);
        ResetProductModel::where('pid', 1)->update(['product_name' => rand(100, 999)]);

        DB::commit();
    }

    private function createDeadlock4()
    {
        DB::beginTransaction();

        ResetProductModel::where('pid', 2)->update(['product_name' => rand(100, 999)]);
        ResetProductModel::where('pid', 1)->update(['product_name' => rand(100, 999)]);
        
        DB::commit();
    }

    private function createBench($count, $callback)
    {
        // 进程数量
        $pids = [];
        for ($i = 0; $i < $count; ++$i)
        {
            $pid = pcntl_fork();
            if ($pid < 0) {
                // 主进程
                throw new \Exception('创建子进程失败: ' . $i);
            } else if ($pid > 0) {
                // 主进程
                $pids[] = $pid;
            } else {
            // 子进程
            try {
                $callback($i);
            } catch(\Exception $ex) {
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
        // ResetProductModel::where('pid', 1)->update(['product_name' => 'aaa']);
        //     DB::beginTransaction();
        //     ResetProductModel::where('pid', 2)->update(['product_name' => 'bbb']);
        //         DB::beginTransaction();
        //         ResetProductModel::where('pid', 3)->update(['product_name' => 'ccc']);
        //         DB::commit();
        //     DB::rollBack();
        // DB::commit();

        $txId = $this->beginDistributedTransaction();
        $this->client->put('api/resetProduct/1', [
            'headers' => ['transact_id' => $txId],
            'json' => ['product_name' => 'aaa']
        ]);
            $txId2 = $this->beginDistributedTransaction();
            $txId2 = implode('-', [$txId, $txId2]);
            $this->client->put('api/resetProduct/2', [
                'headers' => ['transact_id' => $txId2],
                'json' => ['product_name' => 'bbb']
            ]);

                $txId3 = $this->beginDistributedTransaction();
                $txId3 = implode('-', [$txId2, $txId3]);
                $this->client->put('api/resetProduct/3', [
                    'headers' => ['transact_id' => $txId3],
                    'json' => ['product_name' => 'ccc']
                ]);

                $this->commitDistributedTransaction($txId3);

            $this->rollbackDistributedTransaction($txId2);

        $this->commitDistributedTransaction($txId);
    }


    private function beginDistributedTransaction()
    {
        return session_create_id();
    }

    private function commitDistributedTransaction($transactId)
    {
        $response = $this->client->post('/api/resetTransaction/commit', [
            'headers' => ['transact_id' => $transactId]
        ]);
        return $response->getStatusCode();
    }

    private function rollbackDistributedTransaction($transactId)
    {
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
