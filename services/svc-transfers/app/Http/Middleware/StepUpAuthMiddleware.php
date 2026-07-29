<?php

namespace App\Http\Middleware;

use BP\Common\Http\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decision 3.6: for transfers over a threshold, requires the JWT to carry
 * evidence of step-up authentication (acr=step-up or amr with mfa). Runs
 * BEFORE the idempotency middleware on purpose: a rejection for missing
 * step-up must not be cached under the Idempotency-Key, because the
 * client may retry the same operation with a different JWT after
 * re-authenticating.
 */
class StepUpAuthMiddleware
{
    public function __construct(private readonly float $threshold) {}

    public function handle(Request $request, Closure $next): Response
    {
        $amount = (float) $request->input('amount', 0);

        if ($amount <= $this->threshold) {
            return $next($request);
        }

        $claims = $request->attributes->get('jwt_claims', []);
        $acr = $claims['acr'] ?? null;
        $amr = $claims['amr'] ?? [];

        $hasStepUp = $acr === 'step-up' || (is_array($amr) && in_array('mfa', $amr, true));

        if (! $hasStepUp) {
            return ApiResponse::error(
                "This transfer exceeds the threshold of {$this->threshold} and requires step-up authentication.",
                'step_up_required',
                status: 403,
            );
        }

        return $next($request);
    }
}
