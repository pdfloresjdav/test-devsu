<?php

namespace App\Channels;

use App\Contracts\NotificationChannel;
use App\Contracts\NotificationDeliveryException;
use Aws\Ses\SesClient;
use Throwable;

/**
 * Sends transactional email via Amazon SES (decision 3.12). Real for
 * production; SES does have basic support in LocalStack community, but it
 * stays behind the same 'aws' driver to avoid coupling the domain to a
 * specific provider.
 */
class SesNotificationChannel implements NotificationChannel
{
    public function __construct(
        private readonly SesClient $client,
        private readonly string $fromAddress,
    ) {
    }

    public function send(string $recipient, string $subject, string $body): void
    {
        try {
            $this->client->sendEmail([
                'Source' => $this->fromAddress,
                'Destination' => ['ToAddresses' => [$recipient]],
                'Message' => [
                    'Subject' => ['Data' => $subject],
                    'Body' => ['Text' => ['Data' => $body]],
                ],
            ]);
        } catch (Throwable $e) {
            throw new NotificationDeliveryException("SES could not send the email: {$e->getMessage()}", previous: $e);
        }
    }
}
