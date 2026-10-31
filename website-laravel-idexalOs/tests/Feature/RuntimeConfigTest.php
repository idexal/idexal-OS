<?php

namespace Tests\Feature;

use Tests\TestCase;

class RuntimeConfigTest extends TestCase
{
    public function test_desktop_runtime_config_points_the_api_at_the_same_origin(): void
    {
        $this->getJson('/api/runtime-config')
            ->assertOk()
            ->assertJsonPath('denApiUrl', url('/'));
    }

    public function test_runtime_config_allows_desktop_cross_origin_requests(): void
    {
        $this->withHeader('Origin', 'http://localhost:3000')
            ->getJson('/api/runtime-config')
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }
}
