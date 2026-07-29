<?php

namespace App\Channels;

use App\Contracts\NotificationChannel;
use App\Contracts\NotificationDeliveryException;
use Aws\Pinpoint\PinpointClient;
use Throwable;

/**
 * Sends push/SMS via Amazon Pinpoint (decision 3.12). Real for production;
 * not tested against LocalStack because Pinpoint is a paid (Pro) LocalStack
 * feature -- see the 'log' driver decision for development.
 */
class PinpointNotificationChannel implements NotificationChannel
{
    public function __construct(
        private readonly PinpointClient $client,
        private readonly string $applicationId,
        private readonly string $channelType,
    ) {}

    public function send(string $recipient, string $subject, string $body): void
    {
        try {
            $this->client->sendMessages([
                'ApplicationId' => $this->applicationId,
                'MessageRequest' => [
                    'Addresses' => [
                        $recipient => ['ChannelType' => $this->channelType],
                    ],
                    'MessageConfiguration' => [
                        'DefaultMessage' => ['Body' => $body],
                    ],
                ],
            ]);
        } catch (Throwable $e) {
            throw new NotificationDeliveryException("Pinpoint could not send the message: {$e->getMessage()}", previous: $e);
        }
    }
}
