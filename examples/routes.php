<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

Route::prefix('api')->middleware(['api', 'distribute.transact'])->group(function () {
    Route::resource('/resetProduct', \App\Http\Controllers\ResetProductController::class);
});


Route::prefix('api')->middleware('api')->group(function () {
    Route::post('/resetTransaction/rollback', function () {
        $transactId = request()->header('transact_id');
        DB::transaction(function () use ($transactId) {
            DB::table('reset_transaction')->where('transact_id', 'like', $transactId . '%')->delete();
        });

        return 'success';
    });

    Route::post('/resetTransaction/commit', function () {
        $transactId = request()->header('transact_id');
        $rollbackTransact = request()->header('rollback_transact');

        if ($rollbackTransact) {
            $rollbackTransact = json_decode($rollbackTransact, true);
            $rollbackTransact = Arr::dot($rollbackTransact);
            foreach ($rollbackTransact as $txId => $val) {
                $txId = str_replace('.', '-', $txId);
                DB::table('reset_transaction')->where('transact_id', 'like', $txId . '%')->delete();
            }
        }

        $sqlArr = DB::table('reset_transaction')->where('transact_id', 'like', $transactId . '%')->pluck('sql')->toArray();
        if (count($sqlArr) > 0) {
            $sql = implode(';', $sqlArr);
            DB::transaction(function () use ($sql, $transactId) {
                DB::unprepared($sql);
                DB::table('reset_transaction')->where('transact_id', $transactId)->delete();
            });
        }

        return 'success';
    });
});
