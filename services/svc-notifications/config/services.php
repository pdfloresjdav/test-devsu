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

    'notifications' => [
        // 'log' in development (Pinpoint has no free tier support in LocalStack);
        // 'aws' activates real Pinpoint (push/sms) + SES (email).
        'driver' => env('NOTIFICATION_DRIVER', 'log'),
        'table' => env('NOTIFICATIONS_TABLE', 'notification_deliveries'),
        'queue_name' => env('NOTIFICATIONS_QUEUE_NAME', 'notification-events-queue'),
        'dlq_name' => env('NOTIFICATIONS_DLQ_NAME', 'notification-events-dlq'),
        'rule_name' => env('NOTIFICATIONS_RULE_NAME', 'notify-all-domain-events'),
        'queue_url' => env('NOTIFICATIONS_QUEUE_URL'),
        'aws_region' => env('AWS_REGION', 'us-east-1'),
        'pinpoint_application_id' => env('PINPOINT_APPLICATION_ID'),
        'ses_from_address' => env('SES_FROM_ADDRESS', 'notifications@bp.test'),
        'ses_endpoint' => env('AWS_SES_ENDPOINT', env('AWS_ENDPOINT_URL')),

        // Decision 3.12: immediate channel (push) + backup channel (email)
        // for critical events; push only for informational events.
        'channel_map' => [
            'MovementRegistered' => ['push'],
            'TransferCompleted' => ['push', 'email'],
            'TransferFailed' => ['push', 'email'],
            'default' => ['push'],
        ],

        'subject_map' => [
            'MovementRegistered' => 'New movement on your BP account',
            'TransferCompleted' => 'Transfer completed',
            'TransferFailed' => 'Your transfer could not be completed',
        ],
    ],

];
