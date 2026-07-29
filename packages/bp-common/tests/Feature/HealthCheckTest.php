<?php

namespace BP\Common\Tests\Feature;

use BP\Common\Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_get_health_responds_ok_without_additional_configuration(): void
    {
        $this->getJson('/health')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'ok');
    }
}
