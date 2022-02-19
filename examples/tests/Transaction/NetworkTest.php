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

class NetworkTest extends TestCase
{
    private $baseUri = 'http://127.0.0.1:8001';

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

        $stack = HandlerStack::create();
        $stack->push(Middleware::retry(function($retries, Request $request, Response $response, $exception){
            // 重试达到3次就报错
            if ($retries >= 3) {
                return false;
            }

            // 请求失败，继续重试
            if ($exception instanceof ConnectException) {
                return true;
            }

            if ($response) {
                // 如果请求有响应，但是状态码大于等于500，继续重试(这里根据自己的业务而定)
                if ($response->getStatusCode() >= 500) {
                    return true;
                }
            }

            return false;

        }));

        $this->client = new Client([
            'base_uri' => $this->baseUri,
            'timeout' => 5,
            'handler' => $stack,
        ]);
    }

    public function testTimeout()
    {
        $orderCount1 = ResetOrderModel::count();

        $requestId = session_create_id();
        $transactId = RT::beginTransaction();
        $orderNo = rand(1000, 9999);
        $stockQty = 1;
        $amount = 10;

        $startTime = microtime(true);
        // 创建订单
        $response = $this->client->post('/api/resetOrderTest/createWithTimeout', [
            'json' => [
                'order_no' => $orderNo,
                'stock_qty' => $stockQty,
                'amount' => $amount
            ],
            'headers' => [
                'rt_request_id' => $requestId,
                'rt_transact_id' => $transactId,
                
            ],
        ]);

        var_dump(microtime(true) - $startTime);
        $resArr1 = $this->responseToArray($response);
        $this->assertTrue($resArr1['order_no'] == $orderNo);
        RT::commit();

        $orderCount2 = ResetOrderModel::count();

        $this->assertTrue(($orderCount1 + 1) == $orderCount2);
    }

    public function testDuplicateRequest()
    {
        $orderCount1 = ResetOrderModel::count();

        $requestId = session_create_id();
        // $requestId = 111;
        $transactId = RT::beginTransaction();
        $orderNo = rand(1000, 9999);
        $stockQty = 2;
        $amount = 20.55;

        // 创建订单
        $response = $this->client->post('/api/resetOrder', [
            'json' => [
                'order_no' => $orderNo,
                'stock_qty' => $stockQty,
                'amount' => $amount
            ],
            'headers' => [
                'rt_request_id' => $requestId,
                'rt_transact_id' => $transactId,
                
            ],
        ]);
        $resArr1 = $this->responseToArray($response);

        // 重复请求创建订单
        $response = $this->client->post('/api/resetOrder', [
            'json' => [
                'order_no' => $orderNo,
                'stock_qty' => $stockQty,
                'amount' => $amount
            ],
            'headers' => [
                'rt_request_id' => $requestId,
                'rt_transact_id' => $transactId,
                
            ]
        ]);
        $resArr2 = $this->responseToArray($response);
        
        $this->assertTrue($resArr1['id'] == $resArr2['id']);

        RT::commit();

        $orderCount2 = ResetOrderModel::count();

        $this->assertTrue(($orderCount1 + 1) == $orderCount2);
    }

    public function testCheckResult()
    {
        RT::beginTransaction();
        DB::beginTransaction();
        DB::table('reset_order')->setCheckResult(true)->where('id', 1)->update(['stock_qty' => 110]);
        DB::commit();
        RT::commit();

        $this->assertTrue(true);
    }

    public function testLogCommit()
    {
        DB::beginTransaction();
        $transactId = '6abtl2inkilkvus7bftjhdi8nt';
        
        $sqlCollects = DB::table('reset_transact')->where('transact_id', 'like', $transactId . '%')->get();
            if ($sqlCollects->count() > 0) {
                foreach ($sqlCollects as $item) {
                    if ($item->transact_status != RT::STATUS_ROLLBACK) {
                        $result = DB::affectingStatement($item->sql);
                        if ($item->check_result && $result != $item->result) {
                            var_dump("db had been changed by anothor transact_id");
                        }
                    }
                }
            }

        DB::commit();

        $this->assertTrue(true);
    }

    private function responseToArray($response)
    {
        $contents = $response->getBody()->getContents();
        return json_decode($contents, true);
    }
}
