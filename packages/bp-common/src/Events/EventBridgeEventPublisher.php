<?php

namespace BP\Common\Events;

use Aws\EventBridge\EventBridgeClient;
use Throwable;

/**
 * Publishes domain events to Amazon EventBridge. The same client works
 * for LocalStack in development (with its own endpoint) and for real AWS
 * in production -- only the client configuration changes, not the code.
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
            throw new EventPublishingException("Could not publish event [{$detailType}]: {$e->getMessage()}", previous: $e);
        }

        if (($result['FailedEntryCount'] ?? 0) > 0) {
            $reason = $result['Entries'][0]['ErrorMessage'] ?? 'unknown reason';
            throw new EventPublishingException("EventBridge rejected event [{$detailType}]: {$reason}");
        }
    }
}
