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
 * Idempotent provisioning of the local infrastructure this service needs:
 * DLQ + SQS queue (with redrive policy), EventBridge rule that routes
 * domain events to that queue, audit DynamoDB table, and the S3 bucket
 * used by the WORM Archiver. Local equivalent of what gets modeled with
 * IaC on real AWS (Phase 14).
 */
class SetupAuditInfrastructure extends Command
{
    protected $signature = 'audit:setup-infrastructure';

    protected $description = 'Creates the audit SQS queue + DLQ, EventBridge rule, DynamoDB table, and S3 bucket (idempotent)';

    public function handle(SqsClient $sqs, EventBridgeClient $eventBridge, DynamoDbClient $dynamo, S3Client $s3): int
    {
        $queueUrl = $this->createSqsQueues($sqs);
        $this->createEventBridgeRule($eventBridge, $sqs, $queueUrl);
        $this->createDynamoTable($dynamo);
        $this->createS3Bucket($s3);

        $this->info("Done. QUEUE_URL for .env: {$queueUrl}");

        return self::SUCCESS;
    }

    private function createSqsQueues(SqsClient $sqs): string
    {
        $dlqName = config('services.audit.dlq_name');
        $queueName = config('services.audit.queue_name');

        $dlqUrl = $this->createQueueIfNotExists($sqs, $dlqName);
        $dlqArn = $sqs->getQueueAttributes(['QueueUrl' => $dlqUrl, 'AttributeNames' => ['QueueArn']])['Attributes']['QueueArn'];

        $queueUrl = $this->createQueueIfNotExists($sqs, $queueName, [
            'RedrivePolicy' => json_encode([
                'deadLetterTargetArn' => $dlqArn,
                'maxReceiveCount' => '3',
            ]),
        ]);

        $this->info("SQS queues ready: {$queueName} (DLQ: {$dlqName})");

        return $queueUrl;
    }

    /**
     * @param array<string, string> $attributes
     */
    private function createQueueIfNotExists(SqsClient $sqs, string $name, array $attributes = []): string
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

    private function createEventBridgeRule(EventBridgeClient $eventBridge, SqsClient $sqs, string $queueUrl): void
    {
        $busName = config('bp-common.events.event_bus_name');
        $ruleName = config('services.audit.rule_name');
        $queueArn = $sqs->getQueueAttributes(['QueueUrl' => $queueUrl, 'AttributeNames' => ['QueueArn']])['Attributes']['QueueArn'];

        try {
            $eventBridge->putRule([
                'Name' => $ruleName,
                'EventBusName' => $busName,
                // Every domain event published on the bus gets audited
                // (business decision: ALL customer actions are recorded).
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

        // Queue policy so EventBridge can send it messages.
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

        $this->info("EventBridge rule [{$ruleName}] routing bus [{$busName}] to the audit queue.");
    }

    private function createDynamoTable(DynamoDbClient $dynamo): void
    {
        $table = config('services.audit.table');

        try {
            $dynamo->describeTable(['TableName' => $table]);
            $this->info("Table [{$table}] already existed.");

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
        $this->info("Table [{$table}] created.");
    }

    private function createS3Bucket(S3Client $s3): void
    {
        $bucket = config('services.audit.bucket');

        try {
            $s3->headBucket(['Bucket' => $bucket]);
            $this->info("Bucket [{$bucket}] already existed.");

            return;
        } catch (S3Exception $e) {
            if ($e->getStatusCode() !== 404) {
                throw $e;
            }
        }

        $s3->createBucket(['Bucket' => $bucket]);
        $this->info("Bucket [{$bucket}] created.");
    }
}
