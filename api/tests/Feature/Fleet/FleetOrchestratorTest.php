<?php

namespace Tests\Feature\Fleet;

use App\Models\FleetAgent;
use App\Services\Fleet\FleetOrchestrator;
use App\Services\Fleet\FleetTokenService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class FleetOrchestratorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set(['fleet.commander_token' => 'cmd_test']);
    }

    public function test_dispatches_to_all_agents(): void
    {
        $tokenService = app(FleetTokenService::class);

        foreach (['unit-a', 'unit-b', 'unit-c'] as $id) {
            $plain = $tokenService->generateAgentToken();
            FleetAgent::create([
                'agent_id' => $id,
                'token_hash' => $tokenService->hashToken($plain),
                'token_encrypted' => $tokenService->encryptToken($plain),
            ]);
        }

        $orchestrator = app(FleetOrchestrator::class);
        $task = $orchestrator->createTask('device-status', 'ALL', [], 'commander');

        $this->assertSame(3, $task->total_targets);
        $this->assertCount(3, $task->results);

        $aggregated = $orchestrator->aggregateResults($task);
        $this->assertSame('dispatching', $aggregated['status']);
        $this->assertSame(3, $aggregated['summary']['total']);
    }

    public function test_dispatches_to_multiple_targets(): void
    {
        $tokenService = app(FleetTokenService::class);

        foreach (['x1', 'x2', 'x3'] as $id) {
            $plain = $tokenService->generateAgentToken();
            FleetAgent::create([
                'agent_id' => $id,
                'token_hash' => $tokenService->hashToken($plain),
                'token_encrypted' => $tokenService->encryptToken($plain),
            ]);
        }

        $orchestrator = app(FleetOrchestrator::class);
        $task = $orchestrator->createTask('reboot', ['x1', 'x3'], [], 'commander');

        $this->assertSame(2, $task->total_targets);
        $this->assertEqualsCanonicalizing(['x1', 'x3'], $task->results->pluck('agent_id')->all());
    }

    public function test_rejects_unknown_command(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(FleetOrchestrator::class)->createTask('invalid-cmd', 'ALL', [], 'commander');
    }
}
