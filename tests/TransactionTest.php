<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;

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
