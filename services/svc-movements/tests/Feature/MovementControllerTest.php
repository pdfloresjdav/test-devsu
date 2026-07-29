<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Tests\TestCase;

class MovementControllerTest extends TestCase
{
    public function test_rejects_without_a_token(): void
    {
        $this->getJson('/accounts/1001/movements')
            ->assertStatus(401);
    }

    public function test_validates_the_fields_when_registering(): void
    {
        $token = $this->signToken();
        $accountId = (string) Str::uuid();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/accounts/{$accountId}/movements", ['type' => 'invalid'])
            ->assertStatus(422);
    }

    public function test_registers_a_movement_and_then_shows_up_in_the_listing(): void
    {
        $token = $this->signToken();
        $accountId = (string) Str::uuid();

        $registration = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/accounts/{$accountId}/movements", [
                'type' => 'credit',
                'amount' => 150.5,
                'description' => 'Test deposit',
            ]);

        $registration->assertStatus(201)
            ->assertJsonPath('data.account_id', $accountId)
            ->assertJsonPath('data.amount', 150.5);

        $listing = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/accounts/{$accountId}/movements");

        $listing->assertStatus(200)
            ->assertJsonPath('data.0.description', 'Test deposit');
    }
}
