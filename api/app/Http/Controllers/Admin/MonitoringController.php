<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Observability\HealthCheckService;
use App\Services\Observability\QueueMonitor;

class MonitoringController extends Controller
{
    public function queues(QueueMonitor $monitor)
    {
        return response()->json($monitor->queueDepthMap());
    }

    public function workers(QueueMonitor $monitor)
    {
        return response()->json($monitor->workerHeartbeats());
    }

    public function failed(QueueMonitor $monitor)
    {
        return response()->json($monitor->recentFailedJobs());
    }

    public function health(HealthCheckService $health)
    {
        return response()->json($health->check());
    }
}
