<?php

namespace App\Jobs;

use App\Models\FleetTaskResult;
use App\Services\Fleet\FleetAgentDispatcher;
use App\Services\Fleet\FleetOrchestrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchFleetCommandJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $taskResultId,
    ) {}

    public function handle(FleetAgentDispatcher $dispatcher, FleetOrchestrator $orchestrator): void
    {
        $result = FleetTaskResult::query()->find($this->taskResultId);

        if (! $result) {
            return;
        }

        $dispatcher->dispatch($result);

        if ($result->task) {
            $orchestrator->refreshTaskStatus($result->task);
        }
    }
}
