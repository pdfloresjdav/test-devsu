<?php

namespace App\Console\Commands;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;
use Illuminate\Console\Command;

class SetupMovementsTable extends Command
{
    protected $signature = 'movements:setup-table';

    protected $description = 'Creates the movements DynamoDB table if it does not exist (idempotent)';

    public function handle(DynamoDbClient $client): int
    {
        $table = config('services.movements.table');

        try {
            $client->describeTable(['TableName' => $table]);
            $this->info("Table [{$table}] already exists.");

            return self::SUCCESS;
        } catch (DynamoDbException $e) {
            if ($e->getAwsErrorCode() !== 'ResourceNotFoundException') {
                throw $e;
            }
        }

        $client->createTable([
            'TableName' => $table,
            'AttributeDefinitions' => [
                ['AttributeName' => 'account_id', 'AttributeType' => 'S'],
                ['AttributeName' => 'sort_key', 'AttributeType' => 'S'],
            ],
            'KeySchema' => [
                ['AttributeName' => 'account_id', 'KeyType' => 'HASH'],
                ['AttributeName' => 'sort_key', 'KeyType' => 'RANGE'],
            ],
            'BillingMode' => 'PAY_PER_REQUEST',
        ]);

        $client->waitUntil('TableExists', ['TableName' => $table]);

        $this->info("Table [{$table}] created.");

        return self::SUCCESS;
    }
}
