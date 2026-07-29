<?php

namespace BP\Common\Auth;

use BP\Common\Http\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resource Server: validates the JWT (and, if enabled, the DPoP proof) of
 * every incoming request. Doesn't implement login or token issuance --
 * that's Auth0/Okta's job (or the local mock-oidc), per decision 3.5.
 */
class JwtAuthMiddleware
{
    public function __construct(
        private readonly JwtValidator $jwtValidator,
        private readonly DpopValidator $dpopValidator,
        private readonly bool $dpopEnforced,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractBearerToken($request);

        if ($token === null) {
            return ApiResponse::error('Missing Authorization Bearer header.', 'missing_token', status: 401);
        }

        try {
            $claims = $this->jwtValidator->validate($token);
        } catch (JwtValidationException $e) {
            return ApiResponse::error($e->getMessage(), 'invalid_token', status: 401);
        }

        if ($this->dpopEnforced) {
            $dpopError = $this->validateDpop($request, $claims);
            if ($dpopError !== null) {
                return $dpopError;
            }
        }

        $request->attributes->set('jwt_claims', $claims);

        return $next($request);
    }

    private function extractBearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');

        if (! str_starts_with($header, 'Bearer ')) {
            return null;
        }

        return substr($header, strlen('Bearer '));
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function validateDpop(Request $request, array $claims): ?Response
    {
        $dpopProof = $request->header('DPoP');

        if ($dpopProof === null) {
            return ApiResponse::error('Missing DPoP header.', 'missing_dpop', status: 401);
        }

        $expectedThumbprint = $claims['cnf']['jkt'] ?? null;

        try {
            $this->dpopValidator->validate(
                dpopProof: $dpopProof,
                httpMethod: $request->method(),
                httpUri: $request->url(),
                expectedAccessTokenThumbprint: $expectedThumbprint,
            );
        } catch (DpopValidationException $e) {
            return ApiResponse::error($e->getMessage(), 'invalid_dpop', status: 401);
        }

        return null;
    }
}
