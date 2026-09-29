<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_root_route_is_available(): void
    {
        $this->getJson('/')->assertOk()->assertJsonStructure([
            'service',
            'api',
            'health',
            'admin',
        ]);

        $this->get('/')->assertRedirect('/admin');
    }
}
