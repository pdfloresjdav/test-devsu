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
        // KYC: 'fake' in development; 'http' activates real Onfido/iProov.
        'kyc_driver' => env('KYC_DRIVER', 'fake'),
        'kyc_base_url' => env('KYC_BASE_URL'),
        'kyc_api_key' => env('KYC_API_KEY'),

        // Identity: 'fake' in development (mock-oidc has no real Management
        // API); 'auth0' activates the real Auth0 Management API.
        'identity_driver' => env('IDENTITY_DRIVER', 'fake'),
        'auth0_management_url' => env('AUTH0_MANAGEMENT_URL'),
        'auth0_management_token' => env('AUTH0_MANAGEMENT_TOKEN'),

        // Liveness: 'fake' in development; 'aws' activates real AWS Rekognition.
        'liveness_driver' => env('LIVENESS_DRIVER', 'fake'),
        'aws_region' => env('AWS_REGION', 'us-east-1'),
    ],

];
