<?php

namespace App\Http\Controllers;

use App\Models\ResetStorageModel;
use Illuminate\Http\Request;
use Laravel\ResetTransaction\Facades\RT;

class ResetStorageController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        //
        return ResetStorageModel::paginate();
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
        return ResetStorageModel::create($request->input());
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
        $item = ResetStorageModel::find($id);
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
        $item = ResetStorageModel::findOrFail($id);
        if ($request->has('decr_stock_qty')) {
            $decrQty = (float) $request->input('decr_stock_qty');
            $ret = $item->where('stock_qty', '>', $decrQty)->decrement('stock_qty', $decrQty);
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
        $item = ResetStorageModel::findOrFail($id);
        $ret = $item->delete();
        return ['result' => $ret];
    }

    public function updateWithCommit(Request $request, $id)
    {
        $item = ResetStorageModel::findOrFail($id);
        RT::beginTransaction();

        if ($request->has('decr_stock_qty')) {
            $decrQty = (float) $request->input('decr_stock_qty');
            $ret = $item->where('stock_qty', '>', $decrQty)->decrement('stock_qty', $decrQty);
        } else {
            $ret = $item->update($request->input());
        }
        
        RT::commit();

        return ['result' => $ret];
    }
}
