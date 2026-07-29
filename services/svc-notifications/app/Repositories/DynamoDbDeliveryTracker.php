<?php

namespace App\Repositories;

use App\Contracts\DeliveryTracker;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Illuminate\Support\Str;

/**
 * Persists the delivery status of each notification in DynamoDB (same
 * local/real-AWS pattern as the rest of the project). Key: actor
 * (partition) + sort_key = "timestamp#delivery_id" (range).
 */
class DynamoDbDeliveryTracker implements DeliveryTracker
{
    public function __construct(
        private readonly DynamoDbClient $client,
        private readonly Marshaler $marshaler,
        private readonly string $table,
    ) {}

    public function register(string $actor, string $channel, string $action, string $status, ?string $failureReason = null): void
    {
        $timestamp = now()->toIso8601ZuluString('microsecond');
        $deliveryId = (string) Str::uuid();

        $this->client->putItem([
            'TableName' => $this->table,
            'Item' => $this->marshaler->marshalItem([
                'actor' => $actor,
                'sort_key' => "{$timestamp}#{$deliveryId}",
                'delivery_id' => $deliveryId,
                'channel' => $channel,
                'action' => $action,
                'status' => $status,
                'failure_reason' => $failureReason,
                'timestamp' => $timestamp,
            ]),
        ]);
    }
}
