<?php

namespace Tests\Feature;

use Tests\TestCase;

class CustomerControllerTest extends TestCase
{
    public function test_rejects_the_query_without_a_token(): void
    {
        $this->getJson('/customers/1001')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'missing_token');
    }

    public function test_composes_the_data_of_an_existing_customer(): void
    {
        $token = $this->signToken();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/customers/1001')
            ->assertStatus(200)
            ->assertJsonPath('data.customer_id', '1001')
            ->assertJsonPath('data.name', 'Ana Torres')
            ->assertJsonPath('data.segment', 'preferred')
            ->assertJsonPath('data.contact.email', 'ana.torres@example.com');
    }

    public function test_returns_404_for_a_nonexistent_customer(): void
    {
        $token = $this->signToken();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/customers/9999')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'customer_not_found');
    }
}
