<?php

namespace Tests\Feature;

use Tests\TestCase;

class OnboardingControllerTest extends TestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'cliente_id' => '1001',
            'nombre' => 'Ana Torres',
            'email' => 'ana@example.com',
            'documento_identidad' => '1234567890',
            'selfie' => 'selfie-en-base64',
        ], $overrides);
    }

    public function test_no_requiere_token_un_cliente_nuevo_no_tiene_uno(): void
    {
        $this->postJson('/onboarding', $this->payload())->assertStatus(201);
    }

    public function test_aprueba_el_onboarding_y_crea_la_identidad(): void
    {
        $this->postJson('/onboarding', $this->payload())
            ->assertStatus(201)
            ->assertJsonPath('data.estado', 'aprobado')
            ->assertJsonStructure(['data' => ['usuario_id', 'estado']]);
    }

    public function test_rechaza_el_onboarding_si_el_kyc_no_aprueba(): void
    {
        $this->postJson('/onboarding', $this->payload(['documento_identidad' => 'RECHAZA-0001']))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'onboarding_rechazado');
    }

    public function test_valida_los_campos_requeridos(): void
    {
        $this->postJson('/onboarding', ['nombre' => 'Ana'])
            ->assertStatus(422);
    }
}
