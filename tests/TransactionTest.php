<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use App\Models\ResetProductModel;
use GuzzleHttp\Client;

class TransactionTest extends TestCase
{
    public function testCreateWithoutTransact()
    {
        $num = rand(1, 10000);
        $productName = 'php ' . $num;
        $data = [
            'store_id' => 1,
            'product_name' => $productName,
        ];

        $response = $this->post('api/resetProduct', $data);
        $product = $response->json();

        $response = $this->get('/api/resetProduct/' . $product['pid']);
        $product = $response->json();
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

        $response = $this->post('api/resetProduct', $data, $header);
        $product = $response->json();

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

        $response = $this->post('api/resetProduct', $data, $header);
        $product = $response->json();

        $this->commitDistributedTransaction($transactId);

        $response = $this->get('/api/resetProduct/' . $product['pid']);
        $product = $response->json();
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

        $response = $this->post('api/resetProduct', $data, $header);
        $product = $response->json();

        $this->rollbackDistributedTransaction($transactId);

        $response = $this->get('/api/resetProduct/' . $product['pid']);
        $product = $response->json();
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

    public function testForeachDeadlock1()
    {
        $this->initDeadlock();
        try {
            $this->createBench(10, function($i){
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
            $this->createBench(10, function($i){
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
        ResetProductModel::updateOrCreate(['pid' => 1], ['product_name' => rand(100, 999)]);
        ResetProductModel::updateOrCreate(['pid' => 2], ['product_name' => rand(100, 999)]);
    }

    private function createDeadlock1()
    {
        $transactId = $this->beginDistributedTransaction();
        $header = [
            'transact_id' => $transactId,
        ];

        $client = new Client([
            'base_uri' => 'http://127.0.0.1:8000',
            'timeout' => 60,
            'headers' => $header,
        ]);
        $client->request('put', 'api/resetProduct/1', [
            'json' => ['product_name' => rand(100, 999)]
        ]);

        $client->request('put', 'api/resetProduct/2', [
            'json' => ['product_name' => rand(100, 999)]
        ]);

        $client->request('put', 'api/resetProduct/1', [
            'json' => ['product_name' => rand(100, 999)]
        ]);

        $this->commitDistributedTransaction($transactId);

    }

    private function createDeadlock2()
    {
        $transactId = $this->beginDistributedTransaction();
        $header = [
            'transact_id' => $transactId,
        ];

        $client = new Client([
            'base_uri' => 'http://127.0.0.1:8000',
            'timeout' => 60,
            'headers' => $header,
        ]);
        $client->request('put', 'api/resetProduct/2', [
            'json' => ['product_name' => rand(100, 999)]
        ]);

        $client->request('put', 'api/resetProduct/1', [
            'json' => ['product_name' => rand(100, 999)]
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
