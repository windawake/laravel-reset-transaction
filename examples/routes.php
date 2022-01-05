<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Laravel\ResetTransaction\Exception\ResetTransactionException;

Route::prefix('api')->middleware(['api', 'distribute.transact'])->group(function () {
    Route::resource('/resetOrder', \App\Http\Controllers\ResetOrderController::class);
    Route::resource('/resetStorage', \App\Http\Controllers\ResetStorageController::class);
    Route::resource('/resetAccount', \App\Http\Controllers\ResetAccountController::class);

    Route::post('/resetAccountTest/createOrdersCommit', [\App\Http\Controllers\ResetAccountController::class, 'createOrdersCommit']);
    Route::post('/resetAccountTest/createOrdersRollback', [\App\Http\Controllers\ResetAccountController::class, 'createOrdersRollback']);

    Route::put('/resetStorageTest/updateWithCommit/{id}', [\App\Http\Controllers\ResetStorageController::class, 'updateWithCommit']);
    Route::post('/resetOrderTest/createWithTimeout', [\App\Http\Controllers\ResetOrderController::class, 'createWithTimeout']);

    // ab test
    Route::get('/resetOrderTest/deadlockWithLocal', [\App\Http\Controllers\ResetOrderController::class, 'deadlockWithLocal']);
    Route::get('/resetOrderTest/deadlockWithRt', [\App\Http\Controllers\ResetOrderController::class, 'deadlockWithRt']);
    Route::get('/resetOrderTest/createWithLocal', [\App\Http\Controllers\ResetOrderController::class, 'createWithLocal']);
    Route::get('/resetOrderTest/createWithRt', [\App\Http\Controllers\ResetOrderController::class, 'createWithRt']);
    Route::put('/resetOrderTest/updateOrCreate/{id}', [\App\Http\Controllers\ResetOrderController::class, 'updateOrCreate']);
});