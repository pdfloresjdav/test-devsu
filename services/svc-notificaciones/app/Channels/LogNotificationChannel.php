<?php

namespace App\Channels;

use App\Contracts\NotificationChannel;
use Illuminate\Support\Facades\Log;

/**
 * Development driver: instead of really sending the notification, it logs
 * the channel, recipient and content -- enough to demonstrate the full flow
 * (Fase 6 decision) without depending on real Pinpoint/SES or on Pinpoint
 * support in LocalStack (it doesn't have any). Switching to
 * NOTIFICATION_DRIVER=aws in .env activates
 * PinpointNotificationChannel/SesNotificationChannel without touching code.
 */
class LogNotificationChannel implements NotificationChannel
{
    public function __construct(private readonly string $channel)
    {
    }

    public function send(string $recipient, string $subject, string $body): void
    {
        Log::info('Notification sent (log driver)', [
            'channel' => $this->channel,
            'recipient' => $recipient,
            'subject' => $subject,
            'body' => $body,
        ]);
    }
}
