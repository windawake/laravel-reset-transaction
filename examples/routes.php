<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Laravel\ResetTransaction\Exception\ResetTransactionException;
use Laravel\ResetTransaction\Facades\RT;
use Laravel\ResetTransaction\Facades\RTCenter;

Route::prefix('api')->middleware(['api', 'distribute.transact'])->group(function () {
    Route::resource('/resetOrder', \App\Http\Controllers\ResetOrderController::class);
    Route::resource('/resetStorage', \App\Http\Controllers\ResetStorageController::class);
    Route::resource('/resetAccount', \App\Http\Controllers\ResetAccountController::class);

    Route::post('/resetAccountTest/createOrdersCommit', [\App\Http\Controllers\ResetAccountController::class, 'createOrdersCommit']);
    Route::post('/resetAccountTest/createOrdersRollback', [\App\Http\Controllers\ResetAccountController::class, 'createOrdersRollback']);

    Route::put('/resetStorageTest/updateWithCommit/{id}', [\App\Http\Controllers\ResetStorageController::class, 'updateWithCommit']);
    Route::post('/resetOrderTest/createWithTimeout', [\App\Http\Controllers\ResetOrderController::class, 'createWithTimeout']);

    // ab test
    Route::put('/resetOrderTest/updateOrCreate/{id}', [\App\Http\Controllers\ResetOrderController::class, 'updateOrCreate']);
    Route::get('/resetOrderTest/deadlockWithLocal', [\App\Http\Controllers\ResetOrderController::class, 'deadlockWithLocal']);
    Route::get('/resetOrderTest/deadlockWithRt', [\App\Http\Controllers\ResetOrderController::class, 'deadlockWithRt']);

    Route::get('/resetOrderTest/orderWithLocal', [\App\Http\Controllers\ResetOrderController::class, 'orderWithLocal']);
    Route::get('/resetOrderTest/orderWithRt', [\App\Http\Controllers\ResetOrderController::class, 'orderWithRt']);
    Route::get('/resetOrderTest/disorderWithLocal', [\App\Http\Controllers\ResetOrderController::class, 'disorderWithLocal']);
    Route::get('/resetOrderTest/disorderWithRt', [\App\Http\Controllers\ResetOrderController::class, 'disorderWithRt']);
});

Route::prefix('api')->middleware('api')->group(function () {
    Route::post('/resetTransaction/commit', function () {
        $transactId = request('transact_id');
        $transactRollback = request('transact_rollback', []);
        $code = 1;

        RTCenter::commit($transactId, $transactRollback);

        return ['code' => $code, 'transactId' => $transactId];
    });

    Route::post('/resetTransaction/rollback', function () {
        $transactId = request('transact_id');
        $transactRollback = request('transact_rollback', []);
        $code = 1;
        
        RTCenter::rollback($transactId, $transactRollback);

        return ['code' => $code, 'transact_id' => $transactId];
    });
});