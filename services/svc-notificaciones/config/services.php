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

    'notificaciones' => [
        // 'log' en desarrollo (Pinpoint no tiene soporte gratuito en LocalStack);
        // 'aws' activa Pinpoint (push/sms) + SES (email) reales.
        'driver' => env('NOTIFICATION_DRIVER', 'log'),
        'table' => env('NOTIFICACIONES_TABLE', 'notification_deliveries'),
        'queue_name' => env('NOTIFICACIONES_QUEUE_NAME', 'notification-events-queue'),
        'dlq_name' => env('NOTIFICACIONES_DLQ_NAME', 'notification-events-dlq'),
        'rule_name' => env('NOTIFICACIONES_RULE_NAME', 'notify-all-domain-events'),
        'queue_url' => env('NOTIFICACIONES_QUEUE_URL'),
        'aws_region' => env('AWS_REGION', 'us-east-1'),
        'pinpoint_application_id' => env('PINPOINT_APPLICATION_ID'),
        'ses_from_address' => env('SES_FROM_ADDRESS', 'notificaciones@bp.test'),
        'ses_endpoint' => env('AWS_SES_ENDPOINT', env('AWS_ENDPOINT_URL')),

        // Decision 3.12: canal inmediato (push) + canal de respaldo (email)
        // para eventos criticos; solo push para eventos informativos.
        'channel_map' => [
            'MovementRegistered' => ['push'],
            'TransferCompleted' => ['push', 'email'],
            'TransferFailed' => ['push', 'email'],
            'default' => ['push'],
        ],

        'subject_map' => [
            'MovementRegistered' => 'Nuevo movimiento en tu cuenta BP',
            'TransferCompleted' => 'Transferencia completada',
            'TransferFailed' => 'Tu transferencia no se pudo completar',
        ],
    ],

];
