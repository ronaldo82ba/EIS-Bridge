<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;

class HorizonHealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        if (! class_exists(\Laravel\Horizon\Horizon::class)) {
            return response()->json([
                'status' => 'unavailable',
                'message' => 'Horizon is not installed.',
            ], 503);
        }

        try {
            $masters = app(MasterSupervisorRepository::class)->all();
            $running = count($masters) > 0;

            return response()->json([
                'status' => $running ? 'running' : 'stopped',
                'supervisors' => count($masters),
                'checked_at' => now()->toIso8601String(),
            ], $running ? 200 : 503);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to read Horizon status.',
            ], 503);
        }
    }
}
