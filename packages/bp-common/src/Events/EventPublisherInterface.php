<?php

namespace BP\Common\Events;

interface EventPublisherInterface
{
    /**
     * Publishes a domain event to the bus (EventBridge + SQS on AWS,
     * LocalStack locally). See the Pub/Sub pattern, decision 3.13.
     *
     * @param array<string, mixed> $payload
     */
    public function publish(string $detailType, array $payload): void;
}
