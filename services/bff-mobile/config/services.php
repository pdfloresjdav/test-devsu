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

    'onboarding' => [
        // KYC: 'fake' en desarrollo; 'http' activa Onfido/iProov real.
        'kyc_driver' => env('KYC_DRIVER', 'fake'),
        'kyc_base_url' => env('KYC_BASE_URL'),
        'kyc_api_key' => env('KYC_API_KEY'),

        // Identidad: 'fake' en desarrollo (mock-oidc no tiene Management API
        // real); 'auth0' activa la Management API real de Auth0.
        'identity_driver' => env('IDENTITY_DRIVER', 'fake'),
        'auth0_management_url' => env('AUTH0_MANAGEMENT_URL'),
        'auth0_management_token' => env('AUTH0_MANAGEMENT_TOKEN'),

        // Liveness: 'fake' en desarrollo; 'aws' activa AWS Rekognition real.
        'liveness_driver' => env('LIVENESS_DRIVER', 'fake'),
        'aws_region' => env('AWS_REGION', 'us-east-1'),
    ],

];
