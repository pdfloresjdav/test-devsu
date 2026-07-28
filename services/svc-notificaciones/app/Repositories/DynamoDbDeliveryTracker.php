<?php

namespace App\Repositories;

use App\Contracts\DeliveryTracker;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Illuminate\Support\Str;

/**
 * Persiste el estado de entrega de cada notificacion en DynamoDB (mismo
 * patron local/AWS real que el resto del proyecto). Clave: actor
 * (partition) + sort_key = "timestamp#delivery_id" (range).
 */
class DynamoDbDeliveryTracker implements DeliveryTracker
{
    public function __construct(
        private readonly DynamoDbClient $client,
        private readonly Marshaler $marshaler,
        private readonly string $table,
    ) {
    }

    public function registrar(string $actor, string $canal, string $accion, string $estado, ?string $motivoFalla = null): void
    {
        $timestamp = now()->toIso8601ZuluString('microsecond');
        $deliveryId = (string) Str::uuid();

        $this->client->putItem([
            'TableName' => $this->table,
            'Item' => $this->marshaler->marshalItem([
                'actor' => $actor,
                'sort_key' => "{$timestamp}#{$deliveryId}",
                'delivery_id' => $deliveryId,
                'canal' => $canal,
                'accion' => $accion,
                'estado' => $estado,
                'motivo_falla' => $motivoFalla,
                'timestamp' => $timestamp,
            ]),
        ]);
    }
}
