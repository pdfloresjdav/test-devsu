<?php

namespace Tests\Feature;

use App\Repositories\DynamoDbDeliveryTracker;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Corre contra el LocalStack real de docker-compose, igual que el resto
 * de los servicios del proyecto.
 */
class DynamoDbDeliveryTrackerTest extends TestCase
{
    public function test_registra_el_resultado_de_un_envio_exitoso(): void
    {
        $actor = 'actor-' . Str::uuid();

        $tracker = new DynamoDbDeliveryTracker(
            $this->app->make(DynamoDbClient::class),
            $this->app->make(Marshaler::class),
            config('services.notificaciones.table'),
        );

        $tracker->registrar($actor, 'push', 'MovementRegistered', 'enviado');

        $dynamo = $this->app->make(DynamoDbClient::class);
        $marshaler = $this->app->make(Marshaler::class);

        $resultado = $dynamo->query([
            'TableName' => config('services.notificaciones.table'),
            'KeyConditionExpression' => 'actor = :actor',
            'ExpressionAttributeValues' => $marshaler->marshalItem([':actor' => $actor]),
        ]);

        $this->assertCount(1, $resultado['Items']);
        $item = $marshaler->unmarshalItem($resultado['Items'][0]);
        $this->assertSame('enviado', $item['estado']);
        $this->assertSame('push', $item['canal']);
    }
}
