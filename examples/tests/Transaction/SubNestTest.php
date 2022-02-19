<?php

namespace Tests\Transaction;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use App\Models\ResetOrderModel;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Illuminate\Support\Facades\Cache;
use Laravel\ResetTransaction\Facades\RT;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;

class SubNestTest extends TestCase
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
        $requestId = session_create_id();
        session()->put('rt_request_id', $requestId);

        $this->client = new Client([
            'base_uri' => $this->baseUri,
            'timeout' => 5,
        ]);
    }

    public function testCreateOrdersCommit()
    {
        $transactId = RT::beginTransaction();

        ResetOrderModel::create([
            'order_no' => rand(1000, 9999),
            'stock_qty' => 0,
            'amount' => 0
        ]);
        
        // 请求账户服务，减金额
        $response = $this->client->post('/api/resetAccountTest/createOrdersRollback', [
            'headers' => [
                'rt_request_id' => session_create_id(),
                'rt_transact_id' => $transactId,
                
            ]
        ]);
        $resArr = $this->responseToArray($response);

        $this->assertTrue($resArr['result']);

        RT::commit();
    }

    public function testCreateOrdersRollback()
    {
        $transactId = RT::beginTransaction();
        
        // 请求账户服务，减金额
        $response = $this->client->post('/api/resetAccountTest/createOrdersCommit', [
            'headers' => [
                'rt_request_id' => session_create_id(),
                'rt_transact_id' => $transactId,
                
            ]
        ]);
        $resArr = $this->responseToArray($response);

        $this->assertTrue($resArr['result']);

        RT::rollBack();
    }

    public function testNestTransact()
    {
        RT::beginTransaction();
        RT::beginTransaction();
        RT::beginTransaction();

        ResetOrderModel::create([
            'order_no' => rand(1000, 9999),
            'stock_qty' => 0,
            'amount' => 0
        ]);

        RT::commit();
        RT::commit();
        RT::commit();
    }

    private function responseToArray($response)
    {
        $contents = $response->getBody()->getContents();
        return json_decode($contents, true);
    }
}
