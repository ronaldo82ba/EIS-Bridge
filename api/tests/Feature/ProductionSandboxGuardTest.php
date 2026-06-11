<?php

namespace Tests\Feature;

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ProductionSandboxGuardTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->app->detectEnvironment(fn () => 'testing');

        parent::tearDown();
    }

    public function test_health_check_fails_when_production_sandbox_mode_enabled(): void
    {
        config([
            'eis.sandbox_mode' => true,
            'app.debug' => false,
        ]);
        $this->app->detectEnvironment(fn () => 'production');

        $response = $this->getJson('/up');

        $response->assertStatus(500)
            ->assertJson(['status' => 'down']);
    }

    public function test_health_check_passes_when_production_sandbox_mode_disabled(): void
    {
        config([
            'eis.sandbox_mode' => false,
            'app.debug' => false,
        ]);
        $this->app->detectEnvironment(fn () => 'production');

        $response = $this->getJson('/up');

        $response->assertOk()
            ->assertJson(['status' => 'up']);
    }

    public function test_diagnosing_health_event_triggers_sandbox_guard(): void
    {
        config(['eis.sandbox_mode' => true]);
        $this->app->detectEnvironment(fn () => 'production');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('EIS sandbox mode');

        Event::dispatch(new DiagnosingHealth);
    }

    public function test_guard_trusts_live_env_file_when_config_cache_still_says_production(): void
    {
        config([
            'eis.sandbox_mode' => true,
            'app.debug' => false,
        ]);

        $envPath = $this->app->environmentFilePath();
        $original = file_get_contents($envPath);

        try {
            file_put_contents($envPath, "APP_ENV=production\nEIS_SANDBOX_MODE=true\n");
            $this->artisan('config:cache');

            // Forge corrected .env to staging but deploy has not re-cached config yet.
            file_put_contents($envPath, "APP_ENV=staging\nEIS_SANDBOX_MODE=true\n");

            $response = $this->getJson('/up');
            $response->assertOk()->assertJson(['status' => 'up']);
        } finally {
            file_put_contents($envPath, $original);
            $this->artisan('config:clear');
        }
    }
}
