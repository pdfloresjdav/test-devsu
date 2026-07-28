<?php

namespace App\Http\Middleware;

use BP\Common\Http\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decision 3.6: para transferencias sobre un umbral, exige que el JWT
 * traiga evidencia de autenticacion reforzada (acr=step-up o amr con mfa).
 * Corre ANTES del middleware de idempotencia a proposito: un rechazo por
 * falta de step-up no debe quedar cacheado bajo la Idempotency-Key, porque
 * el cliente puede reintentar la misma operacion con un JWT distinto tras
 * reautenticarse.
 */
class StepUpAuthMiddleware
{
    public function __construct(private readonly float $threshold)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $monto = (float) $request->input('monto', 0);

        if ($monto <= $this->threshold) {
            return $next($request);
        }

        $claims = $request->attributes->get('jwt_claims', []);
        $acr = $claims['acr'] ?? null;
        $amr = $claims['amr'] ?? [];

        $tieneStepUp = $acr === 'step-up' || (is_array($amr) && in_array('mfa', $amr, true));

        if (! $tieneStepUp) {
            return ApiResponse::error(
                "Esta transferencia supera el umbral de {$this->threshold} y requiere autenticacion reforzada.",
                'step_up_required',
                status: 403,
            );
        }

        return $next($request);
    }
}
