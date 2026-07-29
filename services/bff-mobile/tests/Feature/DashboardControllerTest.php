<?php

namespace Tests\Feature;

use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    public function test_rechaza_sin_token(): void
    {
        $this->getJson('/dashboard/1001')->assertStatus(401);
    }

    public function test_agrega_cliente_y_movimientos_en_un_solo_contrato(): void
    {
        $this->mockHandler->append(
            new Response(200, [], json_encode(['data' => ['cliente_id' => '1001', 'nombre' => 'Ana Torres']])),
            new Response(200, [], json_encode(['data' => [['movimiento_id' => 'm1', 'monto' => 10]]])),
        );

        $token = $this->signToken();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/dashboard/1001')
            ->assertStatus(200)
            ->assertJsonPath('data.cliente.nombre', 'Ana Torres')
            ->assertJsonPath('data.movimientos_recientes.0.movimiento_id', 'm1');
    }
}
