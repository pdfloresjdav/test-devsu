<?php

namespace Tests\Feature;

use Tests\TestCase;

class LivenessControllerTest extends TestCase
{
    public function test_rejects_without_token(): void
    {
        $this->postJson('/revalidate-liveness', [
            'reference_selfie' => 'ref',
            'new_selfie' => 'new',
        ])->assertStatus(401);
    }

    public function test_revalidates_liveness_successfully(): void
    {
        $token = $this->signToken();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/revalidate-liveness', [
                'reference_selfie' => 'ref',
                'new_selfie' => 'new',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.approved', true);
    }

    public function test_revalidates_liveness_rejected(): void
    {
        $token = $this->signToken();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/revalidate-liveness', [
                'reference_selfie' => 'ref',
                'new_selfie' => 'REJECT',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.approved', false);
    }
}
