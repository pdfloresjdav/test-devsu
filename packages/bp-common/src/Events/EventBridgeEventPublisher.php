<?php

namespace BP\Common\Events;

use Aws\EventBridge\EventBridgeClient;
use Throwable;

/**
 * Publica eventos de dominio en Amazon EventBridge. El mismo cliente sirve
 * para LocalStack en desarrollo (con endpoint propio) y para AWS real en
 * produccion -- solo cambia la configuracion del cliente, no el codigo.
 */
class EventBridgeEventPublisher implements EventPublisherInterface
{
    public function __construct(
        private readonly EventBridgeClient $client,
        private readonly string $eventBusName,
        private readonly string $source,
    ) {
    }

    public function publish(string $detailType, array $payload): void
    {
        try {
            $result = $this->client->putEvents([
                'Entries' => [
                    [
                        'Source' => $this->source,
                        'DetailType' => $detailType,
                        'Detail' => json_encode($payload),
                        'EventBusName' => $this->eventBusName,
                    ],
                ],
            ]);
        } catch (Throwable $e) {
            throw new EventPublishingException("No se pudo publicar el evento [{$detailType}]: {$e->getMessage()}", previous: $e);
        }

        if (($result['FailedEntryCount'] ?? 0) > 0) {
            $reason = $result['Entries'][0]['ErrorMessage'] ?? 'motivo desconocido';
            throw new EventPublishingException("EventBridge rechazo el evento [{$detailType}]: {$reason}");
        }
    }
}
