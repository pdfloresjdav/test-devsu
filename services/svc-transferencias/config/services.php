<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'interbank' => [
        'driver' => env('INTERBANK_DRIVER', 'fake'),
        'base_url' => env('INTERBANK_BASE_URL'),
        'circuit_failure_threshold' => (int) env('INTERBANK_CIRCUIT_FAILURE_THRESHOLD', 3),
        'circuit_cooldown_seconds' => (int) env('INTERBANK_CIRCUIT_COOLDOWN_SECONDS', 30),
    ],

    'transferencias' => [
        'idempotency_ttl_seconds' => (int) env('IDEMPOTENCY_CACHE_TTL_SECONDS', 86400),
        'step_up_threshold' => (float) env('STEP_UP_THRESHOLD', 1000),
    ],

];
