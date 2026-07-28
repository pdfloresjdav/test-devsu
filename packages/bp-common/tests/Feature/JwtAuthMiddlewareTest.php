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
        $router->get('/protegida', fn () => response()->json(['ok' => true]))
            ->middleware(JwtAuthMiddleware::class);
    }

    public function test_rechaza_sin_header_authorization(): void
    {
        $this->getJson('/protegida')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'missing_token');
    }

    public function test_rechaza_un_token_invalido(): void
    {
        $this->withHeader('Authorization', 'Bearer token-basura')
            ->getJson('/protegida')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'invalid_token');
    }

    public function test_permite_pasar_con_un_token_valido(): void
    {
        $token = $this->signToken();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/protegida')
            ->assertStatus(200)
            ->assertJson(['ok' => true]);
    }
}
