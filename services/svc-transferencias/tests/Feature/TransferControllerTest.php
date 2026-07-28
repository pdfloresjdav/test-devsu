<?php

namespace Tests\Feature;

use App\Models\Transferencia;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransferControllerTest extends TestCase
{
    public function test_rechaza_sin_token(): void
    {
        $this->postJson('/transfers', [])->assertStatus(401);
    }

    public function test_camino_feliz_completa_la_transferencia_y_debita_el_saldo(): void
    {
        $origen = $this->crearCuenta(saldo: 1000);
        $token = $this->signToken();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => (string) Str::uuid(),
        ])->postJson('/transfers', [
            'cuenta_origen' => $origen->cuenta_id,
            'cuenta_destino' => 'CUENTA-DESTINO',
            'monto' => 200,
            'descripcion' => 'Pago de prueba',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.estado', Transferencia::ESTADO_COMPLETADA);

        $this->assertEquals(800, $origen->fresh()->saldo);
    }

    public function test_rechaza_sin_idempotency_key(): void
    {
        $origen = $this->crearCuenta();
        $token = $this->signToken();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/transfers', [
                'cuenta_origen' => $origen->cuenta_id,
                'cuenta_destino' => 'CUENTA-DESTINO',
                'monto' => 100,
            ])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'missing_idempotency_key');
    }

    public function test_una_segunda_llamada_con_la_misma_idempotency_key_no_debita_de_nuevo(): void
    {
        $origen = $this->crearCuenta(saldo: 1000);
        $token = $this->signToken();
        $idempotencyKey = (string) Str::uuid();

        $payload = [
            'cuenta_origen' => $origen->cuenta_id,
            'cuenta_destino' => 'CUENTA-DESTINO',
            'monto' => 300,
            'descripcion' => 'Pago unico',
        ];

        $primera = $this->withHeaders(['Authorization' => "Bearer {$token}", 'Idempotency-Key' => $idempotencyKey])
            ->postJson('/transfers', $payload);
        $segunda = $this->withHeaders(['Authorization' => "Bearer {$token}", 'Idempotency-Key' => $idempotencyKey])
            ->postJson('/transfers', $payload);

        $primera->assertStatus(201);
        $segunda->assertStatus(201);
        $this->assertSame($primera->json('data.transferencia_id'), $segunda->json('data.transferencia_id'));

        // Si hubiera debitado dos veces, el saldo seria 400, no 700.
        $this->assertEquals(700, $origen->fresh()->saldo);
        $this->assertSame(1, Transferencia::where('idempotency_key', $idempotencyKey)->count());
    }

    public function test_si_el_banco_destino_rechaza_se_compensa_el_debito(): void
    {
        $origen = $this->crearCuenta(saldo: 1000);
        $token = $this->signToken();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => (string) Str::uuid(),
        ])->postJson('/transfers', [
            'cuenta_origen' => $origen->cuenta_id,
            'cuenta_destino' => 'FALLA-DESTINO',
            'monto' => 250,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.estado', Transferencia::ESTADO_FALLIDA);

        $this->assertEquals(1000, $origen->fresh()->saldo, 'El saldo debio quedar igual tras la compensacion');
    }

    public function test_rechaza_por_saldo_insuficiente(): void
    {
        $origen = $this->crearCuenta(saldo: 50);
        $token = $this->signToken();

        $this->withHeaders(['Authorization' => "Bearer {$token}", 'Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/transfers', [
                'cuenta_origen' => $origen->cuenta_id,
                'cuenta_destino' => 'CUENTA-DESTINO',
                'monto' => 200,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'saldo_insuficiente');
    }

    public function test_rechaza_transferencias_grandes_sin_autenticacion_reforzada(): void
    {
        $origen = $this->crearCuenta(saldo: 5000);
        $token = $this->signToken(); // sin acr/amr de step-up

        $this->withHeaders(['Authorization' => "Bearer {$token}", 'Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/transfers', [
                'cuenta_origen' => $origen->cuenta_id,
                'cuenta_destino' => 'CUENTA-DESTINO',
                'monto' => 5000,
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'step_up_required');

        $this->assertEquals(5000, $origen->fresh()->saldo, 'No debio debitar nada');
    }

    public function test_permite_transferencias_grandes_con_autenticacion_reforzada(): void
    {
        $origen = $this->crearCuenta(saldo: 5000);
        $token = $this->signToken(['acr' => 'step-up']);

        $this->withHeaders(['Authorization' => "Bearer {$token}", 'Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/transfers', [
                'cuenta_origen' => $origen->cuenta_id,
                'cuenta_destino' => 'CUENTA-DESTINO',
                'monto' => 5000,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.estado', Transferencia::ESTADO_COMPLETADA);
    }
}
