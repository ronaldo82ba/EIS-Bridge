<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::middleware(['api_key', 'vendor.ip', 'throttle:vendor-api'])->group(function () {
    Route::post('/transactions', [TransactionController::class, 'store'])
        ->middleware('throttle:vendor-transactions');
    Route::post('/transactions/batch', [TransactionController::class, 'batch'])
        ->middleware('throttle:vendor-transactions');
    Route::get('/transactions/{bridgeTransactionId}', [TransactionController::class, 'show']);
    Route::get('/transactions', [TransactionController::class, 'index']);

    Route::post('/vendors/webhook', [WebhookController::class, 'configure']);
});
