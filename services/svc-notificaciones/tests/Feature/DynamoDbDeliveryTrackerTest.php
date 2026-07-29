<?php

namespace Tests\Feature;

use App\Repositories\DynamoDbDeliveryTracker;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Runs against the real LocalStack from docker-compose, same as the rest
 * of the project's services.
 */
class DynamoDbDeliveryTrackerTest extends TestCase
{
    public function test_records_the_outcome_of_a_successful_send(): void
    {
        $actor = 'actor-' . Str::uuid();

        $tracker = new DynamoDbDeliveryTracker(
            $this->app->make(DynamoDbClient::class),
            $this->app->make(Marshaler::class),
            config('services.notifications.table'),
        );

        $tracker->register($actor, 'push', 'MovementRegistered', 'sent');

        $dynamo = $this->app->make(DynamoDbClient::class);
        $marshaler = $this->app->make(Marshaler::class);

        $result = $dynamo->query([
            'TableName' => config('services.notifications.table'),
            'KeyConditionExpression' => 'actor = :actor',
            'ExpressionAttributeValues' => $marshaler->marshalItem([':actor' => $actor]),
        ]);

        $this->assertCount(1, $result['Items']);
        $item = $marshaler->unmarshalItem($result['Items'][0]);
        $this->assertSame('sent', $item['status']);
        $this->assertSame('push', $item['channel']);
    }
}
