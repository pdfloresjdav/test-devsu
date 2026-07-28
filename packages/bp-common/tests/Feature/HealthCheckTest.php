<?php

namespace BP\Common\Tests\Feature;

use BP\Common\Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_get_health_responde_ok_sin_configuracion_adicional(): void
    {
        $this->getJson('/health')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'ok');
    }
}
