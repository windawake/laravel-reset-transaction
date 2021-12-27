<?php

namespace App\Http\Controllers;

use App\Models\ResetAccountModel;
use DB;
use Illuminate\Http\Request;
use Laravel\ResetTransaction\Facades\RT;
use GuzzleHttp\Client;

class ResetAccountController extends Controller
{
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

    public function createOrders()
    {
        $baseUri = 'http://127.0.0.1:8000';
        $client = new Client([
            'base_uri' => $baseUri,
            'timeout' => 60,
        ]);

        $transactId = RT::beginTransaction();
        
        $client->post('/api/resetOrder', [
            'json' => [
                'order_no' => rand(1000, 9999),
                'stock_qty' => 1,
                'amount' => 4
            ],
            'headers' => [
                'transact_id' => $transactId,
                'transact_connection' => 'service_order'
            ]
        ]);

        $client->put('/api/resetStorage/1', [
            'json' => [
                'decr_stock_qty' => 1
            ],
            'headers' => [
                'transact_id' => $transactId,
                'transact_connection' => 'service_storage'
            ]
        ]);

        ResetAccountModel::where('amount', '>', 4)->decrement('amount', 4);

        RT::commit();

        return ['total' => 1];
    }
}
