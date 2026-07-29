<?php

namespace Tests\Feature;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use BP\Common\Events\EventPublisherInterface;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 5 acceptance criterion: an event published by Transfers or
 * Movements shows up persisted in the local audit store. This exercises
 * the full real chain: EventBridge -> rule -> SQS -> "audit:consume"
 * command -> DynamoDB (+ WORM archiver in S3), all against the real
 * LocalStack from docker-compose.
 */
class ConsumeAuditEventsCommandTest extends TestCase
{
    public function test_a_published_event_ends_up_persisted_in_dynamodb(): void
    {
        $actor = 'actor-e2e-'.Str::uuid();

        $this->app->make(EventPublisherInterface::class)->publish('MovementRegistered', [
            'movement_id' => (string) Str::uuid(),
            'account_id' => 'ACCOUNT-E2E',
            'actor' => $actor,
            'amount' => 42.5,
        ]);

        // Give EventBridge time to deliver the message to the SQS queue.
        sleep(2);

        $this->artisan('audit:consume', ['--once' => true])->assertExitCode(0);

        $dynamo = $this->app->make(DynamoDbClient::class);
        $marshaler = $this->app->make(Marshaler::class);

        $result = $dynamo->query([
            'TableName' => config('services.audit.table'),
            'KeyConditionExpression' => 'actor = :actor',
            'ExpressionAttributeValues' => $marshaler->marshalItem([':actor' => $actor]),
        ]);

        $this->assertCount(1, $result['Items'], 'The published event should have ended up audited for that actor');

        $item = $marshaler->unmarshalItem($result['Items'][0]);
        $this->assertSame('MovementRegistered', $item['action']);
        $this->assertSame('ACCOUNT-E2E', $item['detail']['account_id']);
    }
}
