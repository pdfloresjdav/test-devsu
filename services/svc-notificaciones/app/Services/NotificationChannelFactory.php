<?php

namespace App\Services;

use App\Channels\LogNotificationChannel;
use App\Channels\PinpointNotificationChannel;
use App\Channels\SesNotificationChannel;
use App\Contracts\NotificationChannel;
use Aws\Pinpoint\PinpointClient;
use Aws\Ses\SesClient;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Resolves the channel adapter based on NOTIFICATION_DRIVER: 'log' (dev,
 * demonstrates the full flow without real providers) or 'aws' (Pinpoint
 * for push/sms, SES for email). Switching drivers is configuration only.
 */
class NotificationChannelFactory
{
    public function __construct(private readonly Container $app)
    {
    }

    public function make(string $channel): NotificationChannel
    {
        if (config('services.notifications.driver') !== 'aws') {
            return new LogNotificationChannel($channel);
        }

        return match ($channel) {
            'push', 'sms' => new PinpointNotificationChannel(
                $this->app->make(PinpointClient::class),
                config('services.notifications.pinpoint_application_id'),
                $channel === 'push' ? 'GCM' : 'SMS',
            ),
            'email' => new SesNotificationChannel(
                $this->app->make(SesClient::class),
                config('services.notifications.ses_from_address'),
            ),
            default => throw new InvalidArgumentException("Unknown channel: [{$channel}]"),
        };
    }
}
