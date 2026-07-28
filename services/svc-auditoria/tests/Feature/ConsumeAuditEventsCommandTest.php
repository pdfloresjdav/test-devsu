<?php

namespace Tests\Feature;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use BP\Common\Events\EventPublisherInterface;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Criterio de aceptacion de la Fase 5: un evento publicado por Transferencias
 * o Movimientos aparece persistido en el store de auditoria local. Aqui se
 * ejerce la cadena completa real: EventBridge -> regla -> SQS -> comando
 * "audit:consume" -> DynamoDB (+ WORM archiver en S3), todo contra el
 * LocalStack real de docker-compose.
 */
class ConsumeAuditEventsCommandTest extends TestCase
{
    public function test_un_evento_publicado_termina_persistido_en_dynamodb(): void
    {
        $actor = 'actor-e2e-' . Str::uuid();

        $this->app->make(EventPublisherInterface::class)->publish('MovementRegistered', [
            'movimiento_id' => (string) Str::uuid(),
            'cuenta_id' => 'CUENTA-E2E',
            'actor' => $actor,
            'monto' => 42.5,
        ]);

        // Da tiempo a que EventBridge entregue el mensaje a la cola SQS.
        sleep(2);

        $this->artisan('audit:consume', ['--once' => true])->assertExitCode(0);

        $dynamo = $this->app->make(DynamoDbClient::class);
        $marshaler = $this->app->make(Marshaler::class);

        $resultado = $dynamo->query([
            'TableName' => config('services.auditoria.table'),
            'KeyConditionExpression' => 'actor = :actor',
            'ExpressionAttributeValues' => $marshaler->marshalItem([':actor' => $actor]),
        ]);

        $this->assertCount(1, $resultado['Items'], 'El evento publicado debio terminar auditado para ese actor');

        $item = $marshaler->unmarshalItem($resultado['Items'][0]);
        $this->assertSame('MovementRegistered', $item['accion']);
        $this->assertSame('CUENTA-E2E', $item['detalle']['cuenta_id']);
    }
}
