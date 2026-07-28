<?php

namespace App\Channels;

use App\Contracts\NotificationChannel;
use App\Contracts\NotificationDeliveryException;
use Aws\Ses\SesClient;
use Throwable;

/**
 * Envia email transaccional via Amazon SES (decision 3.12). Real para
 * produccion; SES si tiene soporte basico en LocalStack community, pero se
 * mantiene detras del mismo driver 'aws' para no acoplar el dominio a un
 * proveedor especifico.
 */
class SesNotificationChannel implements NotificationChannel
{
    public function __construct(
        private readonly SesClient $client,
        private readonly string $fromAddress,
    ) {
    }

    public function send(string $destinatario, string $subject, string $body): void
    {
        try {
            $this->client->sendEmail([
                'Source' => $this->fromAddress,
                'Destination' => ['ToAddresses' => [$destinatario]],
                'Message' => [
                    'Subject' => ['Data' => $subject],
                    'Body' => ['Text' => ['Data' => $body]],
                ],
            ]);
        } catch (Throwable $e) {
            throw new NotificationDeliveryException("SES no pudo enviar el email: {$e->getMessage()}", previous: $e);
        }
    }
}
