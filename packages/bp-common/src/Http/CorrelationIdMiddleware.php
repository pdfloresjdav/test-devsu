<?php

namespace BP\Common\Http;

use Closure;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

/**
 * Propaga (o genera) un X-Correlation-Id por request, para poder rastrear
 * una operacion a traves de SPA/App -> BFF -> microservicios -> eventos
 * asincronos (Auditoria, Notificaciones). Necesario porque AWS X-Ray traza
 * la infraestructura, pero este id de negocio es el que aparece en logs
 * aplicativos y en el propio registro de auditoria.
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
