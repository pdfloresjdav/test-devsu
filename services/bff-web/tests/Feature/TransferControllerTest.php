<?php

namespace Tests\Feature;

use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransferControllerTest extends TestCase
{
    public function test_rejects_without_idempotency_key(): void
    {
        $token = $this->signToken();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/transfers', [
                'source_account' => 'A',
                'destination_account' => 'B',
                'amount' => 10,
            ])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'missing_idempotency_key');
    }

    public function test_forwards_the_transfer_with_the_idempotency_key_and_the_token(): void
    {
        $this->mockHandler->append(
            new Response(201, [], json_encode(['data' => ['transfer_id' => 't1', 'status' => 'completed']])),
        );

        $token = $this->signToken();
        $idempotencyKey = (string) Str::uuid();

        $this->withHeaders(['Authorization' => "Bearer {$token}", 'Idempotency-Key' => $idempotencyKey])
            ->postJson('/transfers', [
                'source_account' => 'A',
                'destination_account' => 'B',
                'amount' => 10,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'completed');

        $request = $this->historyContainer[0]['request'];
        $this->assertSame($idempotencyKey, $request->getHeaderLine('Idempotency-Key'));
        $this->assertStringStartsWith('Bearer ', $request->getHeaderLine('Authorization'));
        $this->assertSame('A', json_decode((string) $request->getBody(), true)['source_account']);
    }
}
