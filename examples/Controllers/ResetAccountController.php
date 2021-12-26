<?php

namespace App\Http\Controllers;

use App\Models\ResetAccountModel;
use DB;
use Illuminate\Http\Request;

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
}
