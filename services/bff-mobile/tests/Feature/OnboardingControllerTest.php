<?php

namespace Tests\Feature;

use Tests\TestCase;

class OnboardingControllerTest extends TestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'customer_id' => '1001',
            'name' => 'Ana Torres',
            'email' => 'ana@example.com',
            'identity_document' => '1234567890',
            'selfie' => 'selfie-in-base64',
        ], $overrides);
    }

    public function test_does_not_require_a_token_a_new_customer_does_not_have_one(): void
    {
        $this->postJson('/onboarding', $this->payload())->assertStatus(201);
    }

    public function test_approves_the_onboarding_and_creates_the_identity(): void
    {
        $this->postJson('/onboarding', $this->payload())
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonStructure(['data' => ['user_id', 'status']]);
    }

    public function test_rejects_the_onboarding_if_kyc_does_not_approve(): void
    {
        $this->postJson('/onboarding', $this->payload(['identity_document' => 'REJECT-0001']))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'onboarding_rejected');
    }

    public function test_validates_the_required_fields(): void
    {
        $this->postJson('/onboarding', ['name' => 'Ana'])
            ->assertStatus(422);
    }
}
