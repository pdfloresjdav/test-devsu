<?php

namespace App\Http\Middleware;

use BP\Common\Http\ApiResponse;
use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige un header Idempotency-Key en /transfers y sirve la respuesta ya
 * cacheada si la misma clave ya se proceso -- evita procesar dos veces una
 * transferencia cuando el cliente reintenta por timeout (decision 3.4,
 * diagrama de secuencia 8.1).
 */
class IdempotencyMiddleware
{
    public function __construct(
        private readonly CacheRepository $cache,
        private readonly int $ttlSeconds,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        if (! $idempotencyKey) {
            return ApiResponse::error('Falta el header Idempotency-Key.', 'missing_idempotency_key', status: 400);
        }

        $cacheKey = "idempotency:transfers:{$idempotencyKey}";
        $cached = $this->cache->get($cacheKey);

        if ($cached !== null) {
            return new JsonResponse($cached['body'], $cached['status']);
        }

        $request->attributes->set('idempotency_key', $idempotencyKey);

        $response = $next($request);

        // Si un error no controlado en capas de mas abajo produce una
        // respuesta que no es JSON (ej. la pagina de error generica de
        // Laravel para una Exception no manejada, en vez de un
        // ApiResponse::error), no hay nada serializable para cachear bajo
        // la Idempotency-Key -- y cachear un 500 generico seria ademas
        // incorrecto (el cliente deberia poder reintentar). Se deja pasar
        // la respuesta tal cual, sin ocultar el error real detras de un
        // BadMethodCallException por llamar a getData() en algo que no es
        // un JsonResponse (encontrado en la Fase 11 con un bug real de
        // bcmath faltante que este catch estaba enmascarando).
        if ($response instanceof JsonResponse) {
            $this->cache->put($cacheKey, [
                'status' => $response->getStatusCode(),
                'body' => $response->getData(true),
            ], $this->ttlSeconds);
        }

        return $response;
    }
}
