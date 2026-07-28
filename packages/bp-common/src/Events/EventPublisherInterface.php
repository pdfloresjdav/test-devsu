<?php

namespace BP\Common\Events;

interface EventPublisherInterface
{
    /**
     * Publica un evento de dominio al bus (EventBridge + SQS en AWS,
     * LocalStack en local). Ver patron Pub/Sub, decision 3.13.
     *
     * @param array<string, mixed> $payload
     */
    public function publish(string $detailType, array $payload): void;
}
