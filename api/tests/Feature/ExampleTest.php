<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_root_route_is_available(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
