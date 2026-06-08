<?php

namespace App\Services\Observability;

use Illuminate\Support\Facades\DB;

class QueueMonitor
{
    /**
     * @return array<string, array{waiting: int, processing: int}>
     */
    public function queueDepthMap(): array
    {
        $waitingByQueue = DB::table('jobs')
            ->whereNull('reserved_at')
            ->selectRaw('queue, COUNT(*) as waiting')
            ->groupBy('queue')
            ->pluck('waiting', 'queue');

        $processingByQueue = DB::table('jobs')
            ->whereNotNull('reserved_at')
            ->selectRaw('queue, COUNT(*) as processing')
            ->groupBy('queue')
            ->pluck('processing', 'queue');

        $names = $this->monitoredQueueNames($waitingByQueue->keys(), $processingByQueue->keys());

        $map = [];
        foreach ($names as $name) {
            $map[$name] = [
                'waiting' => (int) ($waitingByQueue[$name] ?? 0),
                'processing' => (int) ($processingByQueue[$name] ?? 0),
            ];
        }

        return $map;
    }

    /**
     * @return list<array{name: string, alive: bool}>
     */
    public function workerHeartbeats(): array
    {
        $monitored = config('observability.monitored_queues', []);
        $activeQueues = $this->activeQueueNames();

        return collect($monitored)->map(fn (string $name) => [
            'name' => $name,
            'alive' => in_array($name, $activeQueues, true),
        ])->values()->all();
    }

    /**
     * @return list<array{id: int, queue: string, exception: string, failed_at: string}>
     */
    public function recentFailedJobs(int $limit = 50): array
    {
        return DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit($limit)
            ->get()
            ->map(fn ($job) => [
                'id' => (int) $job->id,
                'queue' => $job->queue,
                'exception' => strtok($job->exception, "\n") ?: '',
                'failed_at' => $job->failed_at,
            ])
            ->values()
            ->all();
    }

    public function queueStats(): array
    {
        $map = $this->queueDepthMap();

        $failedByQueue = DB::table('failed_jobs')
            ->selectRaw('queue, COUNT(*) as failed')
            ->groupBy('queue')
            ->pluck('failed', 'queue');

        $queues = collect($map)->map(fn (array $stats, string $name) => [
            'name' => $name,
            'depth' => $stats['waiting'] + $stats['processing'],
            'failed' => (int) ($failedByQueue[$name] ?? 0),
            'processed_last_hour' => 0,
        ])->values();

        return [
            'pending_count' => DB::table('jobs')->count(),
            'failed_count' => DB::table('failed_jobs')->count(),
            'queues' => $queues,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    public function workerStatus(): array
    {
        $heartbeats = $this->workerHeartbeats();
        $aliveCount = collect($heartbeats)->where('alive', true)->count();
        $horizonAvailable = class_exists(\Laravel\Horizon\Horizon::class);

        if ($horizonAvailable && app()->bound('redis')) {
            try {
                $masters = app(\Laravel\Horizon\Contracts\MasterSupervisorRepository::class)->all();

                return [
                    'driver' => 'horizon',
                    'status' => count($masters) > 0 ? 'running' : 'stopped',
                    'supervisors' => collect($masters)->map(fn ($master) => [
                        'name' => $master->name ?? null,
                        'status' => $master->status ?? null,
                        'processes' => $master->processes ?? [],
                    ])->values(),
                    'heartbeats' => $heartbeats,
                ];
            } catch (\Throwable) {
                // Fall through to database heartbeat check.
            }
        }

        return [
            'driver' => 'database',
            'status' => $aliveCount > 0 ? 'running' : 'unknown',
            'active_reservations' => $aliveCount,
            'message' => $aliveCount > 0
                ? 'Workers appear active (recent job reservations)'
                : 'No recent worker activity detected',
            'heartbeats' => $heartbeats,
        ];
    }

    /**
     * @param  iterable<string>  ...$extraNames
     * @return list<string>
     */
    private function monitoredQueueNames(iterable ...$extraNames): array
    {
        $names = collect(config('observability.monitored_queues', []));

        foreach ($extraNames as $set) {
            $names = $names->merge($set);
        }

        return $names->unique()->values()->all();
    }

    /**
     * @return list<string>
     */
    private function activeQueueNames(): array
    {
        $horizonAvailable = class_exists(\Laravel\Horizon\Horizon::class);

        if ($horizonAvailable && app()->bound('redis')) {
            try {
                $masters = app(\Laravel\Horizon\Contracts\MasterSupervisorRepository::class)->all();

                $fromHorizon = collect($masters)
                    ->flatMap(fn ($master) => collect($master->processes ?? [])->pluck('queue'))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                if ($fromHorizon !== []) {
                    return array_values(array_unique(array_merge($fromHorizon, $this->recentlyActiveQueues())));
                }
            } catch (\Throwable) {
                // Fall through to database heartbeat check.
            }
        }

        return $this->recentlyActiveQueues();
    }

    /**
     * @return list<string>
     */
    private function recentlyActiveQueues(): array
    {
        return DB::table('jobs')
            ->whereNotNull('reserved_at')
            ->where('reserved_at', '>=', now()->subMinutes(5)->timestamp)
            ->distinct()
            ->pluck('queue')
            ->filter()
            ->values()
            ->all();
    }
}
