<?php

return [
    // 'local' points at the mock-oidc from docker-compose; 'auth0' points at
    // the real tenant. Both modes use the same JWKS validation flow (see
    // decision 3.5/3.6 of the architecture document) -- only the issuer changes.
    'oauth_mode' => env('OAUTH_MODE', 'local'),

    'jwt' => [
        'issuer' => env('OIDC_ISSUER', 'http://localhost:4011'),
        'audience' => env('OIDC_AUDIENCE', 'bp-web'),
        'jwks_cache_ttl' => (int) env('JWKS_CACHE_TTL', 3600),
        // Optional: the URL by which THIS process physically reaches the
        // issuer for discovery/JWKS, when it differs from the `issuer`
        // expected in the `iss` claim (e.g. inside docker-compose, where the
        // issuer is seen as http://mock-oidc:80 from another container but
        // tokens say iss=http://localhost:4011 because that's what the
        // browser saw). If left empty, the same value as `issuer` is used
        // (Phases 1-10).
        'discovery_issuer' => env('OIDC_DISCOVERY_ISSUER'),
    ],

    'auth0' => [
        'domain' => env('AUTH0_DOMAIN'),
        'audience' => env('AUTH0_AUDIENCE'),
    ],

    // Decision 3.6: DPoP binds the access token to a client device key.
    // Can be left disabled locally if the issuer doesn't emit it yet.
    'dpop' => [
        'enforced' => filter_var(env('DPOP_ENFORCED', false), FILTER_VALIDATE_BOOLEAN),
        'iat_leeway_seconds' => (int) env('DPOP_IAT_LEEWAY_SECONDS', 60),
    ],

    // Event bus (EventBridge + SQS, decision 3.13). Empty endpoint = real AWS;
    // locally it points at LocalStack (docker-compose).
    'events' => [
        'endpoint' => env('AWS_EVENTS_ENDPOINT', env('AWS_ENDPOINT_URL')),
        'region' => env('AWS_REGION', 'us-east-1'),
        'event_bus_name' => env('EVENT_BUS_NAME', 'bp-domain-events'),
        'source' => env('EVENT_SOURCE', 'bp.services'),
    ],

    // DynamoDB-backed data repositories (movements, audit). Empty endpoint
    // = real AWS; locally it points at LocalStack.
    'dynamodb' => [
        'endpoint' => env('AWS_DYNAMODB_ENDPOINT', env('AWS_ENDPOINT_URL')),
        'region' => env('AWS_REGION', 'us-east-1'),
    ],

    // SQS queues for the event consumers (Audit, Notifications). Empty
    // endpoint = real AWS; locally it points at LocalStack.
    'sqs' => [
        'endpoint' => env('AWS_SQS_ENDPOINT', env('AWS_ENDPOINT_URL')),
        'region' => env('AWS_REGION', 'us-east-1'),
    ],

    // URLs of the business microservices, used by the shared clients in
    // BP\Common\Clients\* (BFF Web and BFF Mobile).
    'internal_services' => [
        'customer_data_url' => env('CUSTOMER_DATA_BASE_URL', 'http://localhost:8001'),
        'movements_url' => env('MOVEMENTS_BASE_URL', 'http://localhost:8002'),
        'transfers_url' => env('TRANSFERS_BASE_URL', 'http://localhost:8003'),
    ],
];
