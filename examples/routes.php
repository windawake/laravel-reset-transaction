<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Laravel\ResetTransaction\Exception\ResetTransactionException;

Route::prefix('api')->middleware(['api', 'distribute.transact'])->group(function () {
    Route::resource('/resetOrder', \App\Http\Controllers\ResetOrderController::class);
    Route::get('/resetOrder/count', [\App\Http\Controllers\ResetOrderController::class, 'count']);
    Route::resource('/resetStorage', \App\Http\Controllers\ResetStorageController::class);
    Route::resource('/resetAccount', \App\Http\Controllers\ResetAccountController::class);
});


// Route::prefix('api')->middleware('api')->group(function () {
//     Route::post('/resetTransaction/rollback', function () {
//         $transactId = request()->header('transact_id');
//         DB::transaction(function () use ($transactId) {
//             DB::table('reset_transaction')->where('transact_id', 'like', $transactId . '%')->delete();
//         });

//         return 'success';
//     });

//     Route::post('/resetTransaction/commit', function () {
//         $transactId = request()->header('transact_id');
//         $transactRollback = request()->header('transact_rollback');
//         // check the result of SQL execution
//         $transactCheck = request()->header('transact_check');

//         // delete rollback sql
//         if ($transactRollback) {
//             $transactRollback = json_decode($transactRollback, true);
//             $transactRollback = Arr::dot($transactRollback);
//             foreach ($transactRollback as $txId => $val) {
//                 $txId = str_replace('.', '-', $txId);
//                 DB::table('reset_transaction')->where('transact_id', 'like', $txId . '%')->delete();
//             }
//         }

//         $sqlCollects = DB::table('reset_transaction')->where('transact_id', 'like', $transactId . '%')->get();
//         if ($sqlCollects->count() > 0) {
//             DB::transaction(function () use ($sqlCollects, $transactId, $transactCheck) {
//                 foreach ($sqlCollects as $item) {
//                     $result = DB::getPdo()->exec($item->sql);
//                     if ($transactCheck && $result != $item->result) {
//                         throw new ResetTransactionException("db had been changed by anothor transact_id");
//                     }
//                 }
//                 DB::table('reset_transaction')->where('transact_id', 'like', $transactId . '%')->delete();
//             });
//         }

//         return 'success';
//     });
// });
