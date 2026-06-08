<?php

namespace App\Services\Observability;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class HealthCheckService
{
    public function check(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'queue' => $this->checkQueue(),
            'disk' => $this->checkDisk(),
        ];

        $statuses = collect($checks)->pluck('status');
        $overall = $statuses->contains('critical')
            ? 'critical'
            : ($statuses->contains('warning') ? 'warning' : 'healthy');

        return [
            'status' => $overall,
            'checked_at' => now()->toIso8601String(),
            'checks' => $checks,
        ];
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return ['status' => 'healthy', 'message' => 'Database connection OK'];
        } catch (\Throwable $e) {
            return ['status' => 'critical', 'message' => 'Database unreachable'];
        }
    }

    private function checkRedis(): array
    {
        if (config('cache.default') !== 'redis' && config('queue.default') !== 'redis') {
            return ['status' => 'healthy', 'message' => 'Redis not required'];
        }

        try {
            Redis::connection()->ping();

            return ['status' => 'healthy', 'message' => 'Redis connection OK'];
        } catch (\Throwable $e) {
            return ['status' => 'warning', 'message' => 'Redis unreachable'];
        }
    }

    private function checkQueue(): array
    {
        try {
            $pending = DB::table('jobs')->count();
            $threshold = (int) config('observability.queue_backlog_threshold', 100);

            if ($pending > $threshold) {
                return [
                    'status' => 'warning',
                    'message' => "Queue backlog: {$pending} pending jobs",
                    'pending' => $pending,
                ];
            }

            return [
                'status' => 'healthy',
                'message' => 'Queue depth within limits',
                'pending' => $pending,
            ];
        } catch (\Throwable $e) {
            return ['status' => 'critical', 'message' => 'Unable to read queue status'];
        }
    }

    private function checkDisk(): array
    {
        try {
            $path = storage_path();
            $free = disk_free_space($path);
            $total = disk_total_space($path);

            if ($free === false || $total === false || $total === 0) {
                return ['status' => 'warning', 'message' => 'Disk metrics unavailable'];
            }

            $usedPercent = round((1 - ($free / $total)) * 100, 1);

            if ($usedPercent >= 90) {
                return [
                    'status' => 'critical',
                    'message' => "Disk usage at {$usedPercent}%",
                    'used_percent' => $usedPercent,
                ];
            }

            if ($usedPercent >= 80) {
                return [
                    'status' => 'warning',
                    'message' => "Disk usage at {$usedPercent}%",
                    'used_percent' => $usedPercent,
                ];
            }

            return [
                'status' => 'healthy',
                'message' => "Disk usage at {$usedPercent}%",
                'used_percent' => $usedPercent,
            ];
        } catch (\Throwable $e) {
            return ['status' => 'warning', 'message' => 'Disk check failed'];
        }
    }
}
