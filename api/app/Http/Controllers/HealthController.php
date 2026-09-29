<?php

namespace App\Http\Controllers;

use App\Services\Observability\HealthCheckService;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(HealthCheckService $health): JsonResponse
    {
        return response()->json($health->check());
    }
}
