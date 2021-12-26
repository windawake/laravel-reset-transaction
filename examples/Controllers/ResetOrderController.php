<?php

namespace App\Http\Controllers;

use App\Models\ResetOrderModel;
use Illuminate\Http\Request;

class ResetOrderController extends Controller
{
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

    /**
     * Display count.
     *
     * @return \Illuminate\Http\Response
     */
    public function count(Request $request)
    {
        //
        $model = new ResetOrderModel();
        if ($request->has('status')) {
            $model->where('status', $request->input('status'));
        }
        
        return $model->count();
    }
}
