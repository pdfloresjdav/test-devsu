<?php

namespace BP\Common\Http;

use Closure;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

/**
 * Propagates (or generates) an X-Correlation-Id per request, to be able to
 * trace an operation across SPA/App -> BFF -> microservices -> async
 * events (Audit, Notifications). Needed because AWS X-Ray traces the
 * infrastructure, but this business id is the one that shows up in
 * application logs and in the audit record itself.
 */
class CorrelationIdMiddleware
{
    public const HEADER = 'X-Correlation-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = $request->header(self::HEADER) ?: (string) Uuid::uuid4();

        $request->attributes->set('correlation_id', $correlationId);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set(self::HEADER, $correlationId);

        return $response;
    }
}
