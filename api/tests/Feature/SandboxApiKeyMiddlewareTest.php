<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SandboxApiKeyMiddlewareTest extends TestCase
{
    private string $sandboxKey = 'test_sandbox_gate_key_64chars_0123456789abcdef0123456789ab';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set([
            'eis.sandbox_mode' => true,
            'sandbox.api_key' => $this->sandboxKey,
        ]);
    }

    public function test_v1_health_rejects_missing_sandbox_header(): void
    {
        $this->getJson('/v1/health')
            ->assertUnauthorized()
            ->assertJson([
                'error' => 'unauthorized',
                'message' => 'Missing or invalid X-SANDBOX-API-KEY header.',
            ]);
    }

    public function test_v1_health_rejects_invalid_sandbox_header(): void
    {
        $this->getJson('/v1/health', ['X-SANDBOX-API-KEY' => 'wrong-key'])
            ->assertUnauthorized()
            ->assertJson(['error' => 'unauthorized']);
    }

    public function test_v1_health_accepts_valid_sandbox_header(): void
    {
        $this->getJson('/v1/health', ['X-SANDBOX-API-KEY' => $this->sandboxKey])
            ->assertOk()
            ->assertJsonStructure(['status', 'checked_at', 'checks']);
    }

    public function test_up_does_not_require_sandbox_header(): void
    {
        $this->getJson('/up')
            ->assertOk()
            ->assertJson(['status' => 'up']);
    }

    public function test_horizon_health_does_not_require_sandbox_header(): void
    {
        $response = $this->getJson('/horizon-health');

        $this->assertContains($response->status(), [200, 503]);
        $response->assertJsonStructure(['status']);
    }

    public function test_middleware_skipped_when_not_sandbox_mode(): void
    {
        Config::set('eis.sandbox_mode', false);

        $this->getJson('/v1/health')
            ->assertOk()
            ->assertJsonStructure(['status', 'checked_at', 'checks']);
    }

    public function test_middleware_returns_503_when_sandbox_key_unconfigured(): void
    {
        Config::set('sandbox.api_key', '');

        $this->getJson('/v1/health', ['X-SANDBOX-API-KEY' => 'anything'])
            ->assertStatus(503)
            ->assertJson(['error' => 'sandbox_misconfigured']);
    }

    public function test_failed_attempt_is_logged(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Sandbox API key rejected.'
                    && isset($context['ip'], $context['path']);
            });

        $this->getJson('/v1/health', ['X-SANDBOX-API-KEY' => 'bad-key'])
            ->assertUnauthorized();
    }
}
