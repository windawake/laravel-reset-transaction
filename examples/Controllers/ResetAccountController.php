<?php

namespace App\Http\Controllers;

use App\Models\ResetAccountModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Laravel\ResetTransaction\Facades\RT;
use GuzzleHttp\Client;

class ResetAccountController extends Controller
{

    public function __construct()
    {
        DB::setDefaultConnection('service_account');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        //
        return ResetAccountModel::paginate();
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
        return ResetAccountModel::create($request->input());
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
        $item = ResetAccountModel::find($id);
        return $item ?? [];
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * 
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
        $item = ResetAccountModel::findOrFail($id);
        if ($request->has('decr_amount')) {
            $decrAmount = (float) $request->input('decr_amount');
            $ret = $item->where('amount', '>', $decrAmount)->decrement('amount', $decrAmount);
        } else {
            $ret = $item->update($request->input());
        }

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
        $item = ResetAccountModel::findOrFail($id);
        $ret = $item->delete();
        return ['result' => $ret];
    }

    /**
     * transaction create order then commit
     *
     * @return \Illuminate\Http\Response
     */
    public function createOrdersCommit()
    {
        $client = new Client([
            'timeout' => 30,
        ]);
        $orderNo = session_create_id();
        $stockQty = rand(1, 5);
        $amount = rand(1, 50)/10;
        $transactId = RT::beginTransaction();
        
        $client->post('http://127.0.0.1:8003/api/resetOrder', [
            'json' => [
                'order_no' => $orderNo,
                'stock_qty' => $stockQty,
                'amount' => $amount
            ],
            'headers' => [
                'rt_request_id' => session_create_id(),
                'rt_transact_id' => $transactId,
            ]
        ]);

        $response = $client->put('http://127.0.0.1:8004/api/resetStorage/1', [
            'json' => [
                'decr_stock_qty' => $stockQty
            ],
            'headers' => [
                'rt_request_id' => session_create_id(),
                'rt_transact_id' => $transactId,
            ]
        ]);

        $resArr = json_decode($response->getBody()->getContents(), true);

        $rowCount = ResetAccountModel::setCheckResult(true)->where('id', 1)->where('amount', '>', $amount)->decrement('amount', $amount);

        $result = $resArr['result'] && $rowCount>0;

        RT::commit();

        return ['result' => $result];
    }

    /**
     * transaction create order then rollBack
     *
     * @return \Illuminate\Http\Response
     */
    public function createOrdersRollback()
    {
        $client = new Client([
            'timeout' => 30,
        ]);
        $orderNo = session_create_id();
        $stockQty = rand(1, 5);
        $amount = rand(1, 50)/10;
        $transactId = RT::beginTransaction();
        
        $client->post('http://127.0.0.1:8003/api/resetOrder', [
            'json' => [
                'order_no' => $orderNo,
                'stock_qty' => $stockQty,
                'amount' => $amount
            ],
            'headers' => [
                'rt_request_id' => session_create_id(),
                'rt_transact_id' => $transactId,
                
            ]
        ]);

        $client->put('http://127.0.0.1:8004/api/resetStorageTest/updateWithCommit/1', [
            'json' => [
                'decr_stock_qty' => $stockQty
            ],
            'headers' => [
                'rt_request_id' => session_create_id(),
                'rt_transact_id' => $transactId,
                            ]
        ]);

        ResetAccountModel::setCheckResult(true)->where('id', 1)->where('amount', '>', $amount)->decrement('amount', $amount);

        RT::rollBack();

        return ['result' => true];
    }
}
