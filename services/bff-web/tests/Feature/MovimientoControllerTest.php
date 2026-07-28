<?php

namespace Tests\Feature;

use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class MovimientoControllerTest extends TestCase
{
    public function test_rechaza_sin_token(): void
    {
        $this->getJson('/cuentas/1001/movimientos')->assertStatus(401);
    }

    public function test_pasa_los_movimientos_del_servicio_interno(): void
    {
        $this->mockHandler->append(
            new Response(200, [], json_encode(['data' => [['movimiento_id' => 'm1', 'monto' => 20]]])),
        );

        $token = $this->signToken();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/cuentas/1001/movimientos')
            ->assertStatus(200)
            ->assertJsonPath('data.0.movimiento_id', 'm1');

        $peticion = $this->historyContainer[0]['request'];
        $this->assertStringContainsString('/cuentas/1001/movimientos', (string) $peticion->getUri());
        $this->assertStringStartsWith('Bearer ', $peticion->getHeaderLine('Authorization'));
    }
}
