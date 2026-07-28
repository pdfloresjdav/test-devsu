<?php

namespace App\Console\Commands;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\EventBridge\EventBridgeClient;
use Aws\Exception\AwsException;
use Aws\Sqs\SqsClient;
use Illuminate\Console\Command;

/**
 * Provision idempotente: DLQ + cola SQS propia de Notificaciones (con
 * redrive policy), regla de EventBridge que rutea el bus completo hacia
 * esa cola, y la tabla DynamoDB de estado de entrega.
 */
class SetupNotificationInfrastructure extends Command
{
    protected $signature = 'notifications:setup-infrastructure';

    protected $description = 'Crea la cola SQS + DLQ, la regla de EventBridge y la tabla DynamoDB de notificaciones (idempotente)';

    public function handle(SqsClient $sqs, EventBridgeClient $eventBridge, DynamoDbClient $dynamo): int
    {
        $queueUrl = $this->crearColasSqs($sqs);
        $this->crearReglaEventBridge($eventBridge, $sqs, $queueUrl);
        $this->crearTablaDynamo($dynamo);

        $this->info("Listo. QUEUE_URL para .env: {$queueUrl}");

        return self::SUCCESS;
    }

    private function crearColasSqs(SqsClient $sqs): string
    {
        $dlqName = config('services.notificaciones.dlq_name');
        $queueName = config('services.notificaciones.queue_name');

        $dlqUrl = $this->crearColaSiNoExiste($sqs, $dlqName);
        $dlqArn = $sqs->getQueueAttributes(['QueueUrl' => $dlqUrl, 'AttributeNames' => ['QueueArn']])['Attributes']['QueueArn'];

        $queueUrl = $this->crearColaSiNoExiste($sqs, $queueName, [
            'RedrivePolicy' => json_encode([
                'deadLetterTargetArn' => $dlqArn,
                'maxReceiveCount' => '3',
            ]),
        ]);

        $this->info("Colas SQS listas: {$queueName} (DLQ: {$dlqName})");

        return $queueUrl;
    }

    /**
     * @param array<string, string> $attributes
     */
    private function crearColaSiNoExiste(SqsClient $sqs, string $name, array $attributes = []): string
    {
        try {
            return $sqs->getQueueUrl(['QueueName' => $name])['QueueUrl'];
        } catch (AwsException $e) {
            if ($e->getAwsErrorCode() !== 'AWS.SimpleQueueService.NonExistentQueue') {
                throw $e;
            }
        }

        return $sqs->createQueue(['QueueName' => $name, 'Attributes' => $attributes])['QueueUrl'];
    }

    private function crearReglaEventBridge(EventBridgeClient $eventBridge, SqsClient $sqs, string $queueUrl): void
    {
        $busName = config('bp-common.events.event_bus_name');
        $ruleName = config('services.notificaciones.rule_name');
        $queueArn = $sqs->getQueueAttributes(['QueueUrl' => $queueUrl, 'AttributeNames' => ['QueueArn']])['Attributes']['QueueArn'];

        $eventBridge->putRule([
            'Name' => $ruleName,
            'EventBusName' => $busName,
            'EventPattern' => json_encode(['source' => [['prefix' => 'bp.']]]),
            'State' => 'ENABLED',
        ]);

        $eventBridge->putTargets([
            'Rule' => $ruleName,
            'EventBusName' => $busName,
            'Targets' => [['Id' => 'notification-queue-target', 'Arn' => $queueArn]],
        ]);

        $sqs->setQueueAttributes([
            'QueueUrl' => $queueUrl,
            'Attributes' => [
                'Policy' => json_encode([
                    'Version' => '2012-10-17',
                    'Statement' => [[
                        'Effect' => 'Allow',
                        'Principal' => ['Service' => 'events.amazonaws.com'],
                        'Action' => 'sqs:SendMessage',
                        'Resource' => $queueArn,
                    ]],
                ]),
            ],
        ]);

        $this->info("Regla de EventBridge [{$ruleName}] enrutando el bus [{$busName}] hacia la cola de notificaciones.");
    }

    private function crearTablaDynamo(DynamoDbClient $dynamo): void
    {
        $table = config('services.notificaciones.table');

        try {
            $dynamo->describeTable(['TableName' => $table]);
            $this->info("La tabla [{$table}] ya existia.");

            return;
        } catch (DynamoDbException $e) {
            if ($e->getAwsErrorCode() !== 'ResourceNotFoundException') {
                throw $e;
            }
        }

        $dynamo->createTable([
            'TableName' => $table,
            'AttributeDefinitions' => [
                ['AttributeName' => 'actor', 'AttributeType' => 'S'],
                ['AttributeName' => 'sort_key', 'AttributeType' => 'S'],
            ],
            'KeySchema' => [
                ['AttributeName' => 'actor', 'KeyType' => 'HASH'],
                ['AttributeName' => 'sort_key', 'KeyType' => 'RANGE'],
            ],
            'BillingMode' => 'PAY_PER_REQUEST',
        ]);

        $dynamo->waitUntil('TableExists', ['TableName' => $table]);
        $this->info("Tabla [{$table}] creada.");
    }
}
