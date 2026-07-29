<?php

namespace BP\Common\Auth;

use Illuminate\Http\Request;

/**
 * Helper to read the claims that JwtAuthMiddleware leaves on the request.
 * Centralizes how the "actor" (authenticated user) is obtained so every
 * service that publishes domain events does it the same way -- needed so
 * audit can attribute each action to a customer (Phase 5).
 */
class JwtClaims
{
    /**
     * @return array<string, mixed>
     */
    public static function from(Request $request): array
    {
        return $request->attributes->get('jwt_claims', []);
    }

    public static function actor(Request $request, string $default = 'system'): string
    {
        return self::from($request)['sub'] ?? $default;
    }

    /**
     * The raw (undecoded) token, to forward it as-is to an internal
     * microservice -- the BFFs don't reissue their own credentials, they
     * propagate the same JWT that JwtAuthMiddleware already validated
     * (decision 3.5: each service is its own Resource Server).
     */
    public static function bearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');

        return str_starts_with($header, 'Bearer ') ? substr($header, 7) : null;
    }
}
