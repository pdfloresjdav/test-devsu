<?php

namespace Tests\Feature;

use App\Models\Transfer;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransferControllerTest extends TestCase
{
    public function test_rejects_without_a_token(): void
    {
        $this->postJson('/transfers', [])->assertStatus(401);
    }

    public function test_happy_path_completes_the_transfer_and_debits_the_balance(): void
    {
        $source = $this->createAccount(balance: 1000);
        $token = $this->signToken();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => (string) Str::uuid(),
        ])->postJson('/transfers', [
            'source_account' => $source->account_id,
            'destination_account' => 'DESTINATION-ACCOUNT',
            'amount' => 200,
            'description' => 'Test payment',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', Transfer::STATUS_COMPLETED);

        $this->assertEquals(800, $source->fresh()->balance);
    }

    public function test_rejects_without_an_idempotency_key(): void
    {
        $source = $this->createAccount();
        $token = $this->signToken();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/transfers', [
                'source_account' => $source->account_id,
                'destination_account' => 'DESTINATION-ACCOUNT',
                'amount' => 100,
            ])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'missing_idempotency_key');
    }

    public function test_a_second_call_with_the_same_idempotency_key_does_not_debit_again(): void
    {
        $source = $this->createAccount(balance: 1000);
        $token = $this->signToken();
        $idempotencyKey = (string) Str::uuid();

        $payload = [
            'source_account' => $source->account_id,
            'destination_account' => 'DESTINATION-ACCOUNT',
            'amount' => 300,
            'description' => 'One-time payment',
        ];

        $first = $this->withHeaders(['Authorization' => "Bearer {$token}", 'Idempotency-Key' => $idempotencyKey])
            ->postJson('/transfers', $payload);
        $second = $this->withHeaders(['Authorization' => "Bearer {$token}", 'Idempotency-Key' => $idempotencyKey])
            ->postJson('/transfers', $payload);

        $first->assertStatus(201);
        $second->assertStatus(201);
        $this->assertSame($first->json('data.transfer_id'), $second->json('data.transfer_id'));

        // If it had debited twice, the balance would be 400, not 700.
        $this->assertEquals(700, $source->fresh()->balance);
        $this->assertSame(1, Transfer::where('idempotency_key', $idempotencyKey)->count());
    }

    public function test_if_the_destination_bank_rejects_it_the_debit_is_compensated(): void
    {
        $source = $this->createAccount(balance: 1000);
        $token = $this->signToken();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => (string) Str::uuid(),
        ])->postJson('/transfers', [
            'source_account' => $source->account_id,
            'destination_account' => 'FAIL-DESTINATION',
            'amount' => 250,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', Transfer::STATUS_FAILED);

        $this->assertEquals(1000, $source->fresh()->balance, 'The balance should have stayed the same after compensation');
    }

    public function test_rejects_due_to_insufficient_balance(): void
    {
        $source = $this->createAccount(balance: 50);
        $token = $this->signToken();

        $this->withHeaders(['Authorization' => "Bearer {$token}", 'Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/transfers', [
                'source_account' => $source->account_id,
                'destination_account' => 'DESTINATION-ACCOUNT',
                'amount' => 200,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'insufficient_balance');
    }

    public function test_rejects_large_transfers_without_step_up_authentication(): void
    {
        $source = $this->createAccount(balance: 5000);
        $token = $this->signToken(); // no step-up acr/amr

        $this->withHeaders(['Authorization' => "Bearer {$token}", 'Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/transfers', [
                'source_account' => $source->account_id,
                'destination_account' => 'DESTINATION-ACCOUNT',
                'amount' => 5000,
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'step_up_required');

        $this->assertEquals(5000, $source->fresh()->balance, 'Nothing should have been debited');
    }

    public function test_allows_large_transfers_with_step_up_authentication(): void
    {
        $source = $this->createAccount(balance: 5000);
        $token = $this->signToken(['acr' => 'step-up']);

        $this->withHeaders(['Authorization' => "Bearer {$token}", 'Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/transfers', [
                'source_account' => $source->account_id,
                'destination_account' => 'DESTINATION-ACCOUNT',
                'amount' => 5000,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', Transfer::STATUS_COMPLETED);
    }
}
