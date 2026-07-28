<?php

namespace App\Channels;

use App\Contracts\NotificationChannel;
use App\Contracts\NotificationDeliveryException;
use Aws\Pinpoint\PinpointClient;
use Throwable;

/**
 * Envia push/SMS via Amazon Pinpoint (decision 3.12). Real para produccion;
 * no se prueba contra LocalStack porque Pinpoint es una funcionalidad de
 * pago (Pro) de LocalStack -- ver decision del driver 'log' para desarrollo.
 */
class PinpointNotificationChannel implements NotificationChannel
{
    public function __construct(
        private readonly PinpointClient $client,
        private readonly string $applicationId,
        private readonly string $channelType,
    ) {
    }

    public function send(string $destinatario, string $subject, string $body): void
    {
        try {
            $this->client->sendMessages([
                'ApplicationId' => $this->applicationId,
                'MessageRequest' => [
                    'Addresses' => [
                        $destinatario => ['ChannelType' => $this->channelType],
                    ],
                    'MessageConfiguration' => [
                        'DefaultMessage' => ['Body' => $body],
                    ],
                ],
            ]);
        } catch (Throwable $e) {
            throw new NotificationDeliveryException("Pinpoint no pudo enviar el mensaje: {$e->getMessage()}", previous: $e);
        }
    }
}
