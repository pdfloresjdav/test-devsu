<?php

namespace Tests\Feature;

use App\Repositories\DynamoDbMovementsRepository;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Runs against the real LocalStack from docker-compose (bp-localstack),
 * following the project's convention of testing against local
 * infrastructure instead of mocking the AWS SDK.
 */
class DynamoDbMovementsRepositoryTest extends TestCase
{
    public function test_registers_and_lists_movements_in_descending_chronological_order(): void
    {
        $repo = new DynamoDbMovementsRepository(
            $this->app->make(DynamoDbClient::class),
            $this->app->make(Marshaler::class),
            config('services.movements.table'),
        );

        $accountId = (string) Str::uuid();

        $first = $repo->register($accountId, 'credit', 100.0, 'initial deposit');
        usleep(2000);
        $second = $repo->register($accountId, 'debit', 30.0, 'purchase');

        $movements = $repo->list($accountId);

        $this->assertCount(2, $movements);
        $this->assertSame($second['movement_id'], $movements[0]['movement_id'], 'The most recent one should come first');
        $this->assertSame($first['movement_id'], $movements[1]['movement_id']);
        $this->assertSame(30.0, $movements[0]['amount']);
    }

    public function test_respects_the_requested_limit(): void
    {
        $repo = new DynamoDbMovementsRepository(
            $this->app->make(DynamoDbClient::class),
            $this->app->make(Marshaler::class),
            config('services.movements.table'),
        );

        $accountId = (string) Str::uuid();

        foreach (range(1, 5) as $i) {
            $repo->register($accountId, 'debit', (float) $i, "movement {$i}");
        }

        $movements = $repo->list($accountId, limit: 2);

        $this->assertCount(2, $movements);
    }
}
