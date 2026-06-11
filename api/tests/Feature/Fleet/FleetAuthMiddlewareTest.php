<?php

namespace Tests\Feature\Fleet;

use App\Models\FleetAgent;
use App\Models\User;
use App\Services\Fleet\FleetTokenService;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FleetAuthMiddlewareTest extends TestCase
{
    private string $commanderToken = 'cmd_test_token_64chars_0123456789abcdef0123456789ab';

    private string $operatorKey = 'op_test_key_64chars_0123456789abcdef0123456789ab';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set([
            'fleet.commander_token' => $this->commanderToken,
            'fleet.operator_key' => $this->operatorKey,
        ]);
    }

    public function test_commander_token_allows_fleet_task_creation(): void
    {
        $tokenService = app(FleetTokenService::class);
        $plain = $tokenService->generateAgentToken();

        FleetAgent::create([
            'agent_id' => 'tablet-001',
            'token_hash' => $tokenService->hashToken($plain),
            'token_encrypted' => $tokenService->encryptToken($plain),
        ]);

        $this->postJson('/v1/fleet/tasks', [
            'command' => 'device-status',
            'targets' => 'tablet-001',
            'payload' => [],
        ], ['X-Commander-Token' => $this->commanderToken])
            ->assertCreated()
            ->assertJsonPath('command', 'device-status')
            ->assertJsonPath('summary.total', 1);
    }

    public function test_agent_token_cannot_target_other_devices(): void
    {
        $tokenService = app(FleetTokenService::class);
        $plain = $tokenService->generateAgentToken();

        FleetAgent::create([
            'agent_id' => 'tablet-001',
            'token_hash' => $tokenService->hashToken($plain),
            'token_encrypted' => $tokenService->encryptToken($plain),
        ]);

        FleetAgent::create([
            'agent_id' => 'tablet-002',
            'token_hash' => $tokenService->hashToken('other'),
            'token_encrypted' => $tokenService->encryptToken('other'),
        ]);

        $this->postJson('/v1/fleet/tasks', [
            'command' => 'reboot',
            'targets' => 'tablet-002',
            'payload' => [],
        ], ['X-Agent-Token' => $plain])
            ->assertForbidden()
            ->assertJson([
                'error' => 'forbidden',
                'message' => 'Agent token may only control its own device.',
            ]);
    }

    public function test_agent_token_allows_self_target(): void
    {
        $tokenService = app(FleetTokenService::class);
        $plain = $tokenService->generateAgentToken();

        FleetAgent::create([
            'agent_id' => 'tablet-001',
            'token_hash' => $tokenService->hashToken($plain),
            'token_encrypted' => $tokenService->encryptToken($plain),
        ]);

        $this->postJson('/v1/fleet/tasks', [
            'command' => 'device-status',
            'targets' => 'tablet-001',
            'payload' => [],
        ], ['X-Agent-Token' => $plain])
            ->assertCreated()
            ->assertJsonPath('summary.total', 1);
    }

    public function test_dashboard_fleet_requires_operator_key(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'vendor_id' => null,
        ]));

        $this->getJson('/api/admin/fleet/agents')
            ->assertUnauthorized()
            ->assertJson([
                'error' => 'unauthorized',
                'message' => 'Missing or invalid operator key.',
            ]);
    }

    public function test_dashboard_fleet_accepts_operator_key(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'vendor_id' => null,
        ]));

        FleetAgent::create([
            'agent_id' => 'tablet-001',
            'token_hash' => 'hash',
            'token_encrypted' => '',
        ]);

        $this->getJson('/api/admin/fleet/agents', ['X-Operator-Key' => $this->operatorKey])
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_dashboard_rejects_commander_token_without_operator_key(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'vendor_id' => null,
        ]));

        $this->getJson('/api/admin/fleet/agents', ['X-Commander-Token' => $this->commanderToken])
            ->assertUnauthorized()
            ->assertJson([
                'error' => 'unauthorized',
                'message' => 'Missing or invalid operator key.',
            ]);
    }

    public function test_commander_token_lists_all_agents(): void
    {
        FleetAgent::create([
            'agent_id' => 'a1',
            'token_hash' => 'h1',
            'token_encrypted' => '',
        ]);
        FleetAgent::create([
            'agent_id' => 'a2',
            'token_hash' => 'h2',
            'token_encrypted' => '',
        ]);

        $this->getJson('/v1/fleet/agents', ['X-Commander-Token' => $this->commanderToken])
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
