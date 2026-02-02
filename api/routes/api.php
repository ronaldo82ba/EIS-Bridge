<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\WebhookController;

Route::middleware('api_key')->group(function () {
    Route::post('/transactions', [TransactionController::class, 'store']);
    Route::post('/transactions/batch', [TransactionController::class, 'batch']);
    Route::get('/transactions/{bridgeTransactionId}', [TransactionController::class, 'show']);
    Route::get('/transactions', [TransactionController::class, 'index']);

    Route::post('/vendors/webhook', [WebhookController::class, 'configure']);
});
