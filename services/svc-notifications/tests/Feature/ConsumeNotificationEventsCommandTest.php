<?php

namespace Tests\Feature;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use BP\Common\Events\EventPublisherInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Acceptance criterion for Fase 6: a test event triggers a notification log
 * with the correct channel and content. Exercises the full real chain:
 * EventBridge -> rule -> SQS -> "notifications:consume" ->
 * LogNotificationChannel (+ DeliveryTracker in DynamoDB), all against the
 * real LocalStack from docker-compose.
 */
class ConsumeNotificationEventsCommandTest extends TestCase
{
    public function test_a_published_event_triggers_a_notification_log_and_is_recorded(): void
    {
        Log::spy();

        $actor = 'actor-e2e-'.Str::uuid();

        $this->app->make(EventPublisherInterface::class)->publish('TransferCompleted', [
            'transfer_id' => (string) Str::uuid(),
            'source_account' => 'ACCOUNT-A',
            'destination_account' => 'ACCOUNT-B',
            'amount' => 250,
            'actor' => $actor,
        ]);

        sleep(2);

        $this->artisan('notifications:consume', ['--once' => true])->assertExitCode(0);

        // TransferCompleted goes through push AND email (channel_map) -> 2 logs.
        Log::shouldHaveReceived('info')->twice()->withArgs(function (string $message, array $context) use ($actor) {
            return $context['recipient'] === $actor
                && str_contains($context['body'], 'ACCOUNT-A');
        });

        $dynamo = $this->app->make(DynamoDbClient::class);
        $marshaler = $this->app->make(Marshaler::class);

        $result = $dynamo->query([
            'TableName' => config('services.notifications.table'),
            'KeyConditionExpression' => 'actor = :actor',
            'ExpressionAttributeValues' => $marshaler->marshalItem([':actor' => $actor]),
        ]);

        $this->assertCount(2, $result['Items'], 'There should be one delivery record per channel (push and email)');
    }
}
