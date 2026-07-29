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
    public function test_cachea_una_respuesta_json_exitosa(): void
    {
        $middleware = new IdempotencyMiddleware(Cache::store(), ttlSeconds: 60);
        $key = (string) Str::uuid();
        $request = Request::create('/transfers', 'POST');
        $request->headers->set('Idempotency-Key', $key);

        $response = $middleware->handle($request, fn () => new JsonResponse(['data' => ['ok' => true]], 201));

        $this->assertSame(201, $response->getStatusCode());
        $this->assertNotNull(Cache::store()->get("idempotency:transfers:{$key}"));
    }

    public function test_no_revienta_ni_cachea_si_la_respuesta_no_es_json(): void
    {
        // Reproduce el caso real de la Fase 11: un error no controlado mas
        // abajo en la pila (ej. una excepcion de PHP sin capturar) puede
        // producir una respuesta generica de Laravel que no es JsonResponse
        // -- antes, esto reventaba con un BadMethodCallException al llamar
        // getData() sobre ella, ocultando el error real detras de uno mas
        // confuso. Ahora simplemente no se cachea y la respuesta original
        // (con el error real) llega tal cual al cliente.
        $middleware = new IdempotencyMiddleware(Cache::store(), ttlSeconds: 60);
        $key = (string) Str::uuid();
        $request = Request::create('/transfers', 'POST');
        $request->headers->set('Idempotency-Key', $key);

        $response = $middleware->handle($request, fn () => new Response('Server Error', 500));

        $this->assertSame(500, $response->getStatusCode());
        $this->assertNull(Cache::store()->get("idempotency:transfers:{$key}"));
    }
}
