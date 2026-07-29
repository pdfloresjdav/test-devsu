<?php

namespace Tests\Feature;

use Tests\TestCase;

class LivenessControllerTest extends TestCase
{
    public function test_rechaza_sin_token(): void
    {
        $this->postJson('/revalidar-liveness', [
            'selfie_referencia' => 'ref',
            'selfie_nueva' => 'nueva',
        ])->assertStatus(401);
    }

    public function test_revalida_liveness_correctamente(): void
    {
        $token = $this->signToken();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/revalidar-liveness', [
                'selfie_referencia' => 'ref',
                'selfie_nueva' => 'nueva',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.aprobado', true);
    }

    public function test_revalida_liveness_rechazada(): void
    {
        $token = $this->signToken();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/revalidar-liveness', [
                'selfie_referencia' => 'ref',
                'selfie_nueva' => 'RECHAZA',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.aprobado', false);
    }
}
