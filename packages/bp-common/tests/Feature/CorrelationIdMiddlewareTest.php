<?php

namespace BP\Common\Tests\Feature;

use BP\Common\Http\CorrelationIdMiddleware;
use BP\Common\Tests\TestCase;

class CorrelationIdMiddlewareTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        $router->get('/with-correlation', fn () => response()->json(['ok' => true]))
            ->middleware(CorrelationIdMiddleware::class);
    }

    public function test_generates_a_correlation_id_when_not_present_in_the_request(): void
    {
        $response = $this->getJson('/with-correlation');

        $response->assertStatus(200);
        $this->assertNotEmpty($response->headers->get(CorrelationIdMiddleware::HEADER));
    }

    public function test_propagates_the_received_correlation_id(): void
    {
        $response = $this->withHeader(CorrelationIdMiddleware::HEADER, 'fixed-id-123')
            ->getJson('/with-correlation');

        $response->assertHeader(CorrelationIdMiddleware::HEADER, 'fixed-id-123');
    }
}
