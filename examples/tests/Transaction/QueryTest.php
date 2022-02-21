<?php

namespace Tests\Transaction;

use App\Models\ResetAccountModel;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use App\Models\ResetOrderModel;
use App\Models\ResetStorageModel;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Laravel\ResetTransaction\Facades\RT;

class QueryTest extends TestCase
{
    private $baseUri = 'http://127.0.0.1:8000';

    /**
     * Client
     *
     * @var \GuzzleHttp\Client
     */
    private $client;

    protected function setUp(): void
    {
        parent::setUp();

        DB::setDefaultConnection('service_order');
    }


    public function testModelBatch()
    {
        $transactId = RT::beginTransaction();
        $orderNo = rand(1000, 9999);
        $stockQty = rand(1, 10);
        $amount = rand(1, 10)/10;

        // $item = ResetOrderModel::create([
        //     'id' => 1,
        //     'order_no' => $orderNo,
        //     'stock_qty' => $stockQty,
        //     'amount' => $amount
        // ]);

        // var_dump($item);

        DB::table('reset_order')->insert([
            [
                'order_no' => $orderNo,
                'stock_qty' => $stockQty,
                'amount' => $amount,
            ],
            [
                'order_no' => $orderNo + 1,
                'stock_qty' => $stockQty + 1,
                'amount' => $amount + 1,
            ]
        ]);

        RT::commitTest();
    }
}
