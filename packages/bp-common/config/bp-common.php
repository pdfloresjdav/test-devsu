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
        // Opcional: URL por la que ESTE proceso alcanza fisicamente al emisor
        // para el discovery/JWKS, cuando difiere del `issuer` esperado en el
        // claim `iss` (ej. dentro de docker-compose, donde el emisor se ve
        // como http://mock-oidc:80 desde otro contenedor pero los tokens
        // dicen iss=http://localhost:4011 porque asi lo vio el navegador).
        // Si se deja vacio, se usa el mismo valor que `issuer` (Fases 1-10).
        'discovery_issuer' => env('OIDC_DISCOVERY_ISSUER'),
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

    // Bus de eventos (EventBridge + SQS, decision 3.13). endpoint vacio = AWS real;
    // en local apunta a LocalStack (docker-compose).
    'events' => [
        'endpoint' => env('AWS_EVENTS_ENDPOINT', env('AWS_ENDPOINT_URL')),
        'region' => env('AWS_REGION', 'us-east-1'),
        'event_bus_name' => env('EVENT_BUS_NAME', 'bp-domain-events'),
        'source' => env('EVENT_SOURCE', 'bp.services'),
    ],

    // Repositorio de datos sobre DynamoDB (movimientos, auditoria). endpoint
    // vacio = AWS real; en local apunta a LocalStack.
    'dynamodb' => [
        'endpoint' => env('AWS_DYNAMODB_ENDPOINT', env('AWS_ENDPOINT_URL')),
        'region' => env('AWS_REGION', 'us-east-1'),
    ],

    // Colas SQS de los consumidores de eventos (Auditoria, Notificaciones).
    // endpoint vacio = AWS real; en local apunta a LocalStack.
    'sqs' => [
        'endpoint' => env('AWS_SQS_ENDPOINT', env('AWS_ENDPOINT_URL')),
        'region' => env('AWS_REGION', 'us-east-1'),
    ],

    // URLs de los microservicios de negocio, usadas por los clientes
    // compartidos de BP\Common\Clients\* (BFF Web y BFF Movil).
    'internal_services' => [
        'datos_basicos_url' => env('DATOS_BASICOS_BASE_URL', 'http://localhost:8001'),
        'movimientos_url' => env('MOVIMIENTOS_BASE_URL', 'http://localhost:8002'),
        'transferencias_url' => env('TRANSFERENCIAS_BASE_URL', 'http://localhost:8003'),
    ],
];
