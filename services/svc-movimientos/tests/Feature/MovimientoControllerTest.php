<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Tests\TestCase;

class MovimientoControllerTest extends TestCase
{
    public function test_rechaza_sin_token(): void
    {
        $this->getJson('/cuentas/1001/movimientos')
            ->assertStatus(401);
    }

    public function test_valida_los_campos_al_registrar(): void
    {
        $token = $this->signToken();
        $cuentaId = (string) Str::uuid();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/cuentas/{$cuentaId}/movimientos", ['tipo' => 'invalido'])
            ->assertStatus(422);
    }

    public function test_registra_un_movimiento_y_luego_aparece_en_el_listado(): void
    {
        $token = $this->signToken();
        $cuentaId = (string) Str::uuid();

        $registro = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/cuentas/{$cuentaId}/movimientos", [
                'tipo' => 'credito',
                'monto' => 150.5,
                'descripcion' => 'Deposito de prueba',
            ]);

        $registro->assertStatus(201)
            ->assertJsonPath('data.cuenta_id', $cuentaId)
            ->assertJsonPath('data.monto', 150.5);

        $listado = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/cuentas/{$cuentaId}/movimientos");

        $listado->assertStatus(200)
            ->assertJsonPath('data.0.descripcion', 'Deposito de prueba');
    }
}
