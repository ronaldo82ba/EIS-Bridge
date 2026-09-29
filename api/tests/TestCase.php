<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // CI sets EIS_SANDBOX_MODE=true so unlicensed vendor fixtures can operate.
        // Do not require X-SANDBOX-API-KEY on every HTTP test; the dedicated
        // middleware suite re-enables this check.
        $this->withoutMiddleware(\App\Http\Middleware\EnsureSandboxApiKey::class);
    }
}
