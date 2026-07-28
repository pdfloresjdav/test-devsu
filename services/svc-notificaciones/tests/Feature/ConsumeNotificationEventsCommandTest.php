<?php

namespace Tests\Feature;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use BP\Common\Events\EventPublisherInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Criterio de aceptacion de la Fase 6: un evento de prueba dispara un log
 * de notificacion con el canal y contenido correctos. Se ejerce la cadena
 * completa real: EventBridge -> regla -> SQS -> "notifications:consume"
 * -> LogNotificationChannel (+ DeliveryTracker en DynamoDB), todo contra
 * el LocalStack real de docker-compose.
 */
class ConsumeNotificationEventsCommandTest extends TestCase
{
    public function test_un_evento_publicado_dispara_un_log_de_notificacion_y_queda_registrado(): void
    {
        Log::spy();

        $actor = 'actor-e2e-' . Str::uuid();

        $this->app->make(EventPublisherInterface::class)->publish('TransferCompleted', [
            'transferencia_id' => (string) Str::uuid(),
            'cuenta_origen' => 'CUENTA-A',
            'cuenta_destino' => 'CUENTA-B',
            'monto' => 250,
            'actor' => $actor,
        ]);

        sleep(2);

        $this->artisan('notifications:consume', ['--once' => true])->assertExitCode(0);

        // TransferCompleted va por push Y email (channel_map) -> 2 logs.
        Log::shouldHaveReceived('info')->twice()->withArgs(function (string $mensaje, array $contexto) use ($actor) {
            return $contexto['destinatario'] === $actor
                && str_contains($contexto['body'], 'CUENTA-A');
        });

        $dynamo = $this->app->make(DynamoDbClient::class);
        $marshaler = $this->app->make(Marshaler::class);

        $resultado = $dynamo->query([
            'TableName' => config('services.notificaciones.table'),
            'KeyConditionExpression' => 'actor = :actor',
            'ExpressionAttributeValues' => $marshaler->marshalItem([':actor' => $actor]),
        ]);

        $this->assertCount(2, $resultado['Items'], 'Debio quedar un registro de entrega por cada canal (push y email)');
    }
}
