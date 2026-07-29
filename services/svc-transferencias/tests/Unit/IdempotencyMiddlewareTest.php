<?php

namespace Tests\Unit;

use App\Http\Middleware\IdempotencyMiddleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdempotencyMiddlewareTest extends TestCase
{
    public function test_caches_a_successful_json_response(): void
    {
        $middleware = new IdempotencyMiddleware(Cache::store(), ttlSeconds: 60);
        $key = (string) Str::uuid();
        $request = Request::create('/transfers', 'POST');
        $request->headers->set('Idempotency-Key', $key);

        $response = $middleware->handle($request, fn () => new JsonResponse(['data' => ['ok' => true]], 201));

        $this->assertSame(201, $response->getStatusCode());
        $this->assertNotNull(Cache::store()->get("idempotency:transfers:{$key}"));
    }

    public function test_does_not_break_or_cache_if_the_response_is_not_json(): void
    {
        // Reproduces the real Phase 11 case: an uncontrolled error further
        // down the stack (e.g. an uncaught PHP exception) can produce a
        // generic Laravel response that isn't a JsonResponse -- before,
        // this broke with a BadMethodCallException when calling getData()
        // on it, hiding the real error behind a more confusing one. Now it
        // simply doesn't get cached and the original response (with the
        // real error) reaches the client as-is.
        $middleware = new IdempotencyMiddleware(Cache::store(), ttlSeconds: 60);
        $key = (string) Str::uuid();
        $request = Request::create('/transfers', 'POST');
        $request->headers->set('Idempotency-Key', $key);

        $response = $middleware->handle($request, fn () => new Response('Server Error', 500));

        $this->assertSame(500, $response->getStatusCode());
        $this->assertNull(Cache::store()->get("idempotency:transfers:{$key}"));
    }
}
