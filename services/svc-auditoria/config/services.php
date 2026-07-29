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

    'audit' => [
        'table' => env('AUDIT_TABLE', 'audit'),
        'bucket' => env('AUDIT_BUCKET', 'bp-audit-worm'),
        'queue_name' => env('AUDIT_QUEUE_NAME', 'audit-events-queue'),
        'dlq_name' => env('AUDIT_DLQ_NAME', 'audit-events-dlq'),
        'rule_name' => env('AUDIT_RULE_NAME', 'audit-all-domain-events'),
        'queue_url' => env('AUDIT_QUEUE_URL'),
        's3_endpoint' => env('AWS_S3_ENDPOINT', env('AWS_ENDPOINT_URL')),
        's3_region' => env('AWS_REGION', 'us-east-1'),
    ],

];
