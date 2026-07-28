<?php

namespace BP\Common\Tests\Feature;

use BP\Common\Http\CorrelationIdMiddleware;
use BP\Common\Tests\TestCase;

class CorrelationIdMiddlewareTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        $router->get('/con-correlation', fn () => response()->json(['ok' => true]))
            ->middleware(CorrelationIdMiddleware::class);
    }

    public function test_genera_un_correlation_id_si_no_viene_en_el_request(): void
    {
        $response = $this->getJson('/con-correlation');

        $response->assertStatus(200);
        $this->assertNotEmpty($response->headers->get(CorrelationIdMiddleware::HEADER));
    }

    public function test_propaga_el_correlation_id_recibido(): void
    {
        $response = $this->withHeader(CorrelationIdMiddleware::HEADER, 'id-fijo-123')
            ->getJson('/con-correlation');

        $response->assertHeader(CorrelationIdMiddleware::HEADER, 'id-fijo-123');
    }
}
