<?php

namespace Tests\Feature;

use Tests\TestCase;

class HorizonHealthTest extends TestCase
{
    public function test_horizon_health_returns_expected_shape(): void
    {
        $response = $this->getJson('/horizon-health');

        $this->assertContains($response->status(), [200, 503]);
        $response->assertJsonStructure(['status']);
    }
}
