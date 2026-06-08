<?php

namespace Tests\Unit;

use App\Services\Observability\HealthCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthCheckServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_expected_structure(): void
    {
        $result = app(HealthCheckService::class)->check();

        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('checked_at', $result);
        $this->assertArrayHasKey('checks', $result);
        $this->assertContains($result['status'], ['healthy', 'warning', 'critical']);

        foreach (['database', 'redis', 'queue', 'disk'] as $component) {
            $this->assertArrayHasKey($component, $result['checks']);
            $this->assertArrayHasKey('status', $result['checks'][$component]);
            $this->assertArrayHasKey('message', $result['checks'][$component]);
        }
    }
}
