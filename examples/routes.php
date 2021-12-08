<?php

use Illuminate\Support\Facades\DB;
use Laravel\ResetTransaction\Exception\ResetTransactionException;

Route::prefix('api')->middleware(['api', 'distribute.transact'])->group(function () {
    Route::resource('/resetProduct', \App\Http\Controllers\ResetProductController::class);
});


Route::prefix('api')->middleware('api')->group(function () {
    Route::post('/resetTransaction/rollback', function () {
        $transactId = request()->header('transact_id');
        DB::transaction(function () use ($transactId) {
            DB::table('reset_transaction')->where('transact_id', 'like', $transactId.'%')->delete();
        });

        return 'success';
    });

    Route::post('/resetTransaction/commit', function () {
        $transactId = request()->header('transact_id');
        $pos = strpos($transactId, '-');
        if ($pos > 0) return 'success';

        $sqlArr = DB::table('reset_transaction')->where('transact_id', 'like', $transactId.'%')->pluck('sql')->toArray();
        if (count($sqlArr) == 0) {
            throw new ResetTransactionException("transact_id not found");
        }

        $sql = implode(';', $sqlArr);
        DB::transaction(function () use ($sql, $transactId) {
            DB::unprepared($sql);
            DB::table('reset_transaction')->where('transact_id', $transactId)->delete();
        });

        return 'success';
    });
});
