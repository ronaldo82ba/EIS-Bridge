<?php

namespace App\Services\Fleet;

use App\Jobs\DispatchFleetCommandJob;
use App\Models\FleetAgent;
use App\Models\FleetTask;
use App\Models\FleetTaskResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FleetOrchestrator
{
    public function __construct(
        private readonly FleetAgentDispatcher $dispatcher,
    ) {}

    /**
     * @param  string|array<int, string>|'ALL'  $targets
     * @param  array<string, mixed>  $payload
     */
    public function createTask(
        string $command,
        string|array $targets,
        array $payload,
        string $authSource,
    ): FleetTask {
        $allowed = config('fleet.allowed_commands', []);

        if (! in_array($command, $allowed, true)) {
            throw new InvalidArgumentException("Unsupported fleet command: {$command}");
        }

        $agents = $this->resolveTargets($targets);

        if ($agents->isEmpty()) {
            throw new InvalidArgumentException('No fleet agents matched the requested targets.');
        }

        return DB::transaction(function () use ($command, $targets, $payload, $authSource, $agents) {
            $normalizedTargets = $targets === 'ALL'
                ? 'ALL'
                : (is_array($targets) ? array_values($targets) : [$targets]);

            $task = FleetTask::create([
                'command' => $command,
                'payload' => $payload,
                'targets' => $normalizedTargets,
                'auth_source' => $authSource,
                'status' => 'dispatching',
                'total_targets' => $agents->count(),
            ]);

            foreach ($agents as $agent) {
                $result = FleetTaskResult::create([
                    'fleet_task_id' => $task->id,
                    'fleet_agent_id' => $agent->id,
                    'agent_id' => $agent->agent_id,
                    'status' => 'pending',
                    'request_payload' => $payload,
                ]);

                DispatchFleetCommandJob::dispatch($result->id);
            }

            return $task->fresh(['results']);
        });
    }

    public function refreshTaskStatus(FleetTask $task): FleetTask
    {
        $results = $task->results()->get();

        $completed = $results->where('status', 'completed')->count();
        $failed = $results->whereIn('status', ['failed', 'timeout'])->count();
        $pending = $results->whereIn('status', ['pending', 'running'])->count();

        $status = match (true) {
            $pending > 0 => 'dispatching',
            $failed > 0 && $completed > 0 => 'partial',
            $failed > 0 => 'failed',
            default => 'completed',
        };

        $task->update([
            'status' => $status,
            'completed_targets' => $completed,
            'failed_targets' => $failed,
            'completed_at' => $pending === 0 ? now() : null,
        ]);

        return $task->fresh(['results']);
    }

    /**
     * @param  string|array<int, string>|'ALL'  $targets
     * @return Collection<int, FleetAgent>
     */
    public function resolveTargets(string|array $targets): Collection
    {
        if ($targets === 'ALL') {
            return FleetAgent::query()->orderBy('agent_id')->get();
        }

        $ids = is_array($targets) ? $targets : [$targets];

        return FleetAgent::query()
            ->whereIn('agent_id', $ids)
            ->orderBy('agent_id')
            ->get();
    }

    public function aggregateResults(FleetTask $task): array
    {
        $task = $this->refreshTaskStatus($task);
        $task->load('results');

        return [
            'id' => $task->id,
            'command' => $task->command,
            'status' => $task->status,
            'targets' => $task->targets,
            'auth_source' => $task->auth_source,
            'summary' => [
                'total' => $task->total_targets,
                'completed' => $task->completed_targets,
                'failed' => $task->failed_targets,
            ],
            'results' => $task->results->map(fn (FleetTaskResult $result) => [
                'id' => $result->id,
                'agent_id' => $result->agent_id,
                'status' => $result->status,
                'response' => $result->response_payload,
                'error' => $result->error_message,
                'duration_ms' => $result->duration_ms,
                'completed_at' => $result->completed_at?->toIso8601String(),
            ])->values()->all(),
            'created_at' => $task->created_at?->toIso8601String(),
            'completed_at' => $task->completed_at?->toIso8601String(),
        ];
    }
}
