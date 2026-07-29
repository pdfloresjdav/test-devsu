<?php

namespace BP\Common\Tests\Feature;

use BP\Common\Auth\JwtAuthMiddleware;
use BP\Common\Tests\TestCase;
use Illuminate\Routing\Router;

class JwtAuthMiddlewareTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        /** @var Router $router */
        $router->get('/protected', fn () => response()->json(['ok' => true]))
            ->middleware(JwtAuthMiddleware::class);
    }

    public function test_rejects_without_authorization_header(): void
    {
        $this->getJson('/protected')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'missing_token');
    }

    public function test_rejects_an_invalid_token(): void
    {
        $this->withHeader('Authorization', 'Bearer garbage-token')
            ->getJson('/protected')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'invalid_token');
    }

    public function test_allows_through_with_a_valid_token(): void
    {
        $token = $this->signToken();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/protected')
            ->assertStatus(200)
            ->assertJson(['ok' => true]);
    }
}
