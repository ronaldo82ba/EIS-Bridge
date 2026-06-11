<?php

use App\Http\Controllers\Fleet\FleetAgentController;
use App\Http\Controllers\Fleet\FleetTaskController;
use Illuminate\Support\Facades\Route;

Route::prefix('fleet')->group(function () {
    Route::post('/agents/register', [FleetAgentController::class, 'register']);

    Route::middleware(['fleet.auth:agent-only'])->group(function () {
        Route::post('/agents/heartbeat', [FleetAgentController::class, 'heartbeat']);
        Route::get('/agents/me/pending-tasks', [FleetAgentController::class, 'pendingTasks']);
        Route::post('/agents/me/task-results/{resultId}', [FleetAgentController::class, 'submitResult']);
    });

    Route::middleware(['fleet.auth:read'])->group(function () {
        Route::get('/agents', [FleetAgentController::class, 'index']);
        Route::get('/tasks/{taskId}', [FleetTaskController::class, 'show']);
    });

    Route::middleware(['fleet.auth:mutate'])->group(function () {
        Route::post('/tasks', [FleetTaskController::class, 'store']);
    });
});
