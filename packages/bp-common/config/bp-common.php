<?php

return [
    // 'local' apunta al mock-oidc de docker-compose; 'auth0' apunta al tenant real.
    // Ambos modos usan el mismo flujo de validacion via JWKS (ver decision 3.5/3.6
    // del documento de arquitectura) -- solo cambia el emisor.
    'oauth_mode' => env('OAUTH_MODE', 'local'),

    'jwt' => [
        'issuer' => env('OIDC_ISSUER', 'http://localhost:4011'),
        'audience' => env('OIDC_AUDIENCE', 'bp-web'),
        'jwks_cache_ttl' => (int) env('JWKS_CACHE_TTL', 3600),
    ],

    'auth0' => [
        'domain' => env('AUTH0_DOMAIN'),
        'audience' => env('AUTH0_AUDIENCE'),
    ],

    // Decision 3.6: DPoP ata el access token a una clave del dispositivo cliente.
    // Puede quedar deshabilitado en local si el emisor no lo emite todavia.
    'dpop' => [
        'enforced' => filter_var(env('DPOP_ENFORCED', false), FILTER_VALIDATE_BOOLEAN),
        'iat_leeway_seconds' => (int) env('DPOP_IAT_LEEWAY_SECONDS', 60),
    ],
];
