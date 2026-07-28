<?php

namespace Tests\Feature;

use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransferControllerTest extends TestCase
{
    public function test_rechaza_sin_idempotency_key(): void
    {
        $token = $this->signToken();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/transferencias', [
                'cuenta_origen' => 'A',
                'cuenta_destino' => 'B',
                'monto' => 10,
            ])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'missing_idempotency_key');
    }

    public function test_reenvia_la_transferencia_con_el_idempotency_key_y_el_token(): void
    {
        $this->mockHandler->append(
            new Response(201, [], json_encode(['data' => ['transferencia_id' => 't1', 'estado' => 'completada']])),
        );

        $token = $this->signToken();
        $idempotencyKey = (string) Str::uuid();

        $this->withHeaders(['Authorization' => "Bearer {$token}", 'Idempotency-Key' => $idempotencyKey])
            ->postJson('/transferencias', [
                'cuenta_origen' => 'A',
                'cuenta_destino' => 'B',
                'monto' => 10,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.estado', 'completada');

        $peticion = $this->historyContainer[0]['request'];
        $this->assertSame($idempotencyKey, $peticion->getHeaderLine('Idempotency-Key'));
        $this->assertStringStartsWith('Bearer ', $peticion->getHeaderLine('Authorization'));
        $this->assertSame('A', json_decode((string) $peticion->getBody(), true)['cuenta_origen']);
    }
}
