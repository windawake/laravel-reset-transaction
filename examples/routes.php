<?php

use Illuminate\Support\Facades\DB;

Route::prefix('api')->middleware(['api','distribute.transact'])->group(function(){
    Route::resource('/resetProduct', \App\Http\Controllers\ResetProductController::class);
});


Route::prefix('api')->middleware('api')->group(function () {
    Route::get('/resetTransaction/rollback', function () {
        $transactId = request('transact_id');
        $code = 1;
        DB::transaction(function () use ($transactId) {
            DB::table('reset_transaction')->where('transact_id', $transactId)->delete();
        });

        return ['code' => $code, 'transactId' => $transactId];
    });

    Route::get('/resetTransaction/commit', function () {
        $transactId = request('transact_id');
        $code = 1;
        $list = DB::table('reset_transaction')->where('transact_id', $transactId)->get();
        DB::transaction(function () use ($list, $transactId) {
            foreach ($list as $item) {
                DB::unprepared($item->sql);
            }

            DB::table('reset_transaction')->where('transact_id', $transactId)->delete();
        });

        return ['code' => $code, 'transact_id' => $transactId];
    });
});
