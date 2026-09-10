<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        // Process env (including CI job env) wins over phpunit.xml unless we reset it.
        // Individual tests that need sandbox mode call Config::set() after boot.
        putenv('EIS_SANDBOX_MODE=false');
        $_ENV['EIS_SANDBOX_MODE'] = 'false';
        $_SERVER['EIS_SANDBOX_MODE'] = 'false';

        parent::setUp();
    }
}
