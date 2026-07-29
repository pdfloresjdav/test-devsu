<?php

namespace App\Http\Middleware;

use BP\Common\Http\ApiResponse;
use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires an Idempotency-Key header on /transfers and serves the already
 * cached response if the same key was already processed -- avoids
 * processing a transfer twice when the client retries due to a timeout
 * (decision 3.4, sequence diagram 8.1).
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
            return ApiResponse::error('Missing Idempotency-Key header.', 'missing_idempotency_key', status: 400);
        }

        $cacheKey = "idempotency:transfers:{$idempotencyKey}";
        $cached = $this->cache->get($cacheKey);

        if ($cached !== null) {
            return new JsonResponse($cached['body'], $cached['status']);
        }

        $request->attributes->set('idempotency_key', $idempotencyKey);

        $response = $next($request);

        // If an uncontrolled error further down the stack produces a
        // response that isn't JSON (e.g. Laravel's generic error page for
        // an unhandled Exception, instead of an ApiResponse::error),
        // there's nothing serializable to cache under the Idempotency-Key
        // -- and caching a generic 500 would also be incorrect (the client
        // should be able to retry). The response is passed through as-is,
        // without hiding the real error behind a BadMethodCallException
        // from calling getData() on something that isn't a JsonResponse
        // (found in Phase 11 with a real missing-bcmath bug that this
        // catch was masking).
        if ($response instanceof JsonResponse) {
            $this->cache->put($cacheKey, [
                'status' => $response->getStatusCode(),
                'body' => $response->getData(true),
            ], $this->ttlSeconds);
        }

        return $response;
    }
}
