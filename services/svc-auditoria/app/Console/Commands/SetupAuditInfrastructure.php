<?php

namespace App\Console\Commands;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\EventBridge\EventBridgeClient;
use Aws\EventBridge\Exception\EventBridgeException;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Aws\Sqs\SqsClient;
use Illuminate\Console\Command;

/**
 * Provision idempotente de la infraestructura local que necesita este
 * servicio: DLQ + cola SQS (con redrive policy), regla de EventBridge que
 * rutea los eventos de dominio hacia esa cola, tabla DynamoDB de auditoria,
 * y el bucket S3 que usa el WORM Archiver. Equivalente local a lo que en
 * AWS real se modela con IaC (Fase 13).
 */
class SetupAuditInfrastructure extends Command
{
    protected $signature = 'audit:setup-infrastructure';

    protected $description = 'Crea la cola SQS + DLQ, la regla de EventBridge, la tabla DynamoDB y el bucket S3 de auditoria (idempotente)';

    public function handle(SqsClient $sqs, EventBridgeClient $eventBridge, DynamoDbClient $dynamo, S3Client $s3): int
    {
        $queueUrl = $this->crearColasSqs($sqs);
        $this->crearReglaEventBridge($eventBridge, $sqs, $queueUrl);
        $this->crearTablaDynamo($dynamo);
        $this->crearBucketS3($s3);

        $this->info("Listo. QUEUE_URL para .env: {$queueUrl}");

        return self::SUCCESS;
    }

    private function crearColasSqs(SqsClient $sqs): string
    {
        $dlqName = config('services.auditoria.dlq_name');
        $queueName = config('services.auditoria.queue_name');

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
        } catch (\Aws\Exception\AwsException $e) {
            if ($e->getAwsErrorCode() !== 'AWS.SimpleQueueService.NonExistentQueue') {
                throw $e;
            }
        }

        return $sqs->createQueue(['QueueName' => $name, 'Attributes' => $attributes])['QueueUrl'];
    }

    private function crearReglaEventBridge(EventBridgeClient $eventBridge, SqsClient $sqs, string $queueUrl): void
    {
        $busName = config('bp-common.events.event_bus_name');
        $ruleName = config('services.auditoria.rule_name');
        $queueArn = $sqs->getQueueAttributes(['QueueUrl' => $queueUrl, 'AttributeNames' => ['QueueArn']])['Attributes']['QueueArn'];

        try {
            $eventBridge->putRule([
                'Name' => $ruleName,
                'EventBusName' => $busName,
                // Todos los eventos de dominio publicados en el bus se
                // auditan (decision de negocio: TODAS las acciones del
                // cliente quedan registradas).
                'EventPattern' => json_encode(['source' => [['prefix' => 'bp.']]]),
                'State' => 'ENABLED',
            ]);

            $eventBridge->putTargets([
                'Rule' => $ruleName,
                'EventBusName' => $busName,
                'Targets' => [['Id' => 'audit-queue-target', 'Arn' => $queueArn]],
            ]);
        } catch (EventBridgeException $e) {
            throw $e;
        }

        // Politica de la cola para que EventBridge pueda enviarle mensajes.
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

        $this->info("Regla de EventBridge [{$ruleName}] enrutando el bus [{$busName}] hacia la cola de auditoria.");
    }

    private function crearTablaDynamo(DynamoDbClient $dynamo): void
    {
        $table = config('services.auditoria.table');

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

    private function crearBucketS3(S3Client $s3): void
    {
        $bucket = config('services.auditoria.bucket');

        try {
            $s3->headBucket(['Bucket' => $bucket]);
            $this->info("El bucket [{$bucket}] ya existia.");

            return;
        } catch (S3Exception $e) {
            if ($e->getStatusCode() !== 404) {
                throw $e;
            }
        }

        $s3->createBucket(['Bucket' => $bucket]);
        $this->info("Bucket [{$bucket}] creado.");
    }
}
