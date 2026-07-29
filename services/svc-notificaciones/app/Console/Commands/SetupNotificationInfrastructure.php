<?php

namespace App\Console\Commands;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\EventBridge\EventBridgeClient;
use Aws\Exception\AwsException;
use Aws\Sqs\SqsClient;
use Illuminate\Console\Command;

/**
 * Idempotent provisioning: Notifications' own DLQ + SQS queue (with a
 * redrive policy), an EventBridge rule that routes the whole bus to that
 * queue, and the delivery-status DynamoDB table.
 */
class SetupNotificationInfrastructure extends Command
{
    protected $signature = 'notifications:setup-infrastructure';

    protected $description = 'Creates the SQS queue + DLQ, the EventBridge rule and the notifications DynamoDB table (idempotent)';

    public function handle(SqsClient $sqs, EventBridgeClient $eventBridge, DynamoDbClient $dynamo): int
    {
        $queueUrl = $this->createSqsQueues($sqs);
        $this->createEventBridgeRule($eventBridge, $sqs, $queueUrl);
        $this->createDynamoTable($dynamo);

        $this->info("Done. QUEUE_URL for .env: {$queueUrl}");

        return self::SUCCESS;
    }

    private function createSqsQueues(SqsClient $sqs): string
    {
        $dlqName = config('services.notifications.dlq_name');
        $queueName = config('services.notifications.queue_name');

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
        } catch (AwsException $e) {
            if ($e->getAwsErrorCode() !== 'AWS.SimpleQueueService.NonExistentQueue') {
                throw $e;
            }
        }

        return $sqs->createQueue(['QueueName' => $name, 'Attributes' => $attributes])['QueueUrl'];
    }

    private function createEventBridgeRule(EventBridgeClient $eventBridge, SqsClient $sqs, string $queueUrl): void
    {
        $busName = config('bp-common.events.event_bus_name');
        $ruleName = config('services.notifications.rule_name');
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

        $this->info("EventBridge rule [{$ruleName}] routing bus [{$busName}] to the notifications queue.");
    }

    private function createDynamoTable(DynamoDbClient $dynamo): void
    {
        $table = config('services.notifications.table');

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
}
