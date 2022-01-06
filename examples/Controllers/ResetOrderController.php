<?php

namespace App\Http\Controllers;

use App\Models\ResetOrderModel;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\ResetTransaction\Facades\RT;

class ResetOrderController extends Controller
{
    public function __construct()
    {
        DB::setDefaultConnection('service_order');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        //
        $query = ResetOrderModel::query();
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }
        return $query->paginate();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
        return ResetOrderModel::create($request->input());
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
        $item = ResetOrderModel::find($id);
        return $item ?? [];
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
        $item = ResetOrderModel::findOrFail($id);
        $ret = $item->update($request->input());
        return ['result' => $ret];
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
        $item = ResetOrderModel::findOrFail($id);
        $ret = $item->delete();
        return ['result' => $ret];
    }

    public function updateOrCreate(Request $request, $id)
    {
        //
        $attr = ['id' => $id];
        $item = ResetOrderModel::updateOrCreate($attr, $request->input());
        return $item;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function createWithTimeout(Request $request)
    {
        $requestId = $request->header('rt_request_id');
        $cache = Cache::store('file');
        $cache->increment($requestId);
        $times = $cache->get($requestId);
        if ($times < 4) {
            sleep(13);
        }

        return ResetOrderModel::create($request->input());
    }

    public function deadlockWithLocal(Request $request)
    {
        DB::beginTransaction();

        $s = ($request->getPort()) % 2;
        for ($i = $s; $i < 3; $i++) {
            $id = $i % 2 + 1;
            $attrs = ['id' => $id];
            $values = ['order_no' => session_create_id()];

            ResetOrderModel::updateOrCreate($attrs, $values);
            usleep(rand(1, 200) * 1000);
        }
        DB::commit();
    }

    public function deadlockWithRt(Request $request)
    {
        $transactId = RT::beginTransaction();
        $s = ($request->getPort()) % 2;
        for ($i = $s; $i < 3; $i++) {
            $id = $i % 2 + 1;
            // $attrs = ['id' => $id];
            // $values = ['order_no' => session_create_id()];
            // ResetOrderModel::updateOrCreate($attrs, $values);
            
            $client = new Client([
                'base_uri' => 'http://127.0.0.1:8002',
                'timeout' => 60,
            ]);
            $client->put('/api/resetOrderTest/updateOrCreate/'.$id, [
                'json' =>['order_no' => session_create_id()],
                'headers' => [
                    'rt_request_id' => session_create_id(),
                    'rt_transact_id' => $transactId,
                    'rt_connection' => 'service_order'
                ]
            ]);
            
            usleep(rand(1, 200) * 1000);
        }
        RT::commit();
    }

    public function orderWithLocal(Request $request)
    {
        DB::beginTransaction();
        usleep(rand(1, 200) * 1000);
        $orderNo = session_create_id();
        $stockQty = rand(1, 5);
        $amount = rand(1, 50)/10;

        $item = ResetOrderModel::create([
            'order_no' => $orderNo,
            'stock_qty' => $stockQty,
            'amount' => $amount
        ]);

        $item->increment('stock_qty');
        DB::commit();
    }

    public function orderWithRt(Request $request)
    {
        RT::beginTransaction();
        usleep(rand(1, 200) * 1000);
        $orderNo = session_create_id();
        $stockQty = rand(1, 5);
        $amount = rand(1, 50)/10;

        $item = ResetOrderModel::create([
            'order_no' => $orderNo,
            'stock_qty' => $stockQty,
            'amount' => $amount
        ]);

        $item->increment('stock_qty');
        RT::commit();
    }

    public function disorderWithLocal()
    {
        DB::beginTransaction();
        usleep(rand(1, 200) * 1000);
        $orderNo = session_create_id();
        $stockQty = rand(1, 5);
        $amount = rand(1, 50)/10;
        $status = rand(1, 3);

        $item = ResetOrderModel::updateOrCreate([
            'id' => rand(1, 10),
        ], [
            'order_no' => $orderNo,
            'stock_qty' => $stockQty,
            'amount' => $amount,
            'status' => $status,
        ]);


        $item = ResetOrderModel::find(rand(1,10));
        if ($item) {
            $item->delete();
        }

        if (rand(0,1) == 0) {
            ResetOrderModel::where('status', $status)->update(['stock_qty' => rand(1, 5)]);
        }

        DB::commit();
    }

    public function disorderWithRt()
    {
        RT::beginTransaction();
        usleep(rand(1, 200) * 1000);
        $orderNo = session_create_id();
        $stockQty = rand(1, 5);
        $amount = rand(1, 50)/10;
        $status = rand(1, 3);

        $item = ResetOrderModel::updateOrCreate([
            'id' => rand(1, 10),
        ], [
            'order_no' => $orderNo,
            'stock_qty' => $stockQty,
            'amount' => $amount,
            'status' => $status,
        ]);


        $item = ResetOrderModel::find(rand(1,10));
        if ($item) {
            $item->delete();
        }

        if (rand(0,1) == 0) {
            ResetOrderModel::where('status', $status)->update(['stock_qty' => rand(1, 5)]);
        }

        RT::commit();
    }
}
