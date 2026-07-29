<?php

namespace Tests\Feature;

use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    public function test_rejects_without_token(): void
    {
        $this->getJson('/dashboard/1001')->assertStatus(401);
    }

    public function test_composes_customer_and_movements_into_a_single_contract(): void
    {
        $this->mockHandler->append(
            new Response(200, [], json_encode(['data' => ['customer_id' => '1001', 'name' => 'Ana Torres']])),
            new Response(200, [], json_encode(['data' => [['movement_id' => 'm1', 'amount' => 10]]])),
        );

        $token = $this->signToken();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/dashboard/1001')
            ->assertStatus(200)
            ->assertJsonPath('data.customer.name', 'Ana Torres')
            ->assertJsonPath('data.recent_movements.0.movement_id', 'm1');
    }

    public function test_propagates_the_error_if_the_customer_does_not_exist(): void
    {
        $this->mockHandler->append(
            new Response(404, [], json_encode(['error' => ['message' => 'Customer not found']])),
        );

        $token = $this->signToken();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/dashboard/9999')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'upstream_error');
    }
}
