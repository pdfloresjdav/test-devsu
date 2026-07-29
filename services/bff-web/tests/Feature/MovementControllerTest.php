<?php

namespace Tests\Feature;

use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class MovementControllerTest extends TestCase
{
    public function test_rejects_without_token(): void
    {
        $this->getJson('/accounts/1001/movements')->assertStatus(401);
    }

    public function test_passes_through_the_movements_from_the_internal_service(): void
    {
        $this->mockHandler->append(
            new Response(200, [], json_encode(['data' => [['movement_id' => 'm1', 'amount' => 20]]])),
        );

        $token = $this->signToken();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/accounts/1001/movements')
            ->assertStatus(200)
            ->assertJsonPath('data.0.movement_id', 'm1');

        $request = $this->historyContainer[0]['request'];
        $this->assertStringContainsString('/accounts/1001/movements', (string) $request->getUri());
        $this->assertStringStartsWith('Bearer ', $request->getHeaderLine('Authorization'));
    }
}
