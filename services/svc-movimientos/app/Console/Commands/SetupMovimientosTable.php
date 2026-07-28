<?php

namespace App\Console\Commands;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;
use Illuminate\Console\Command;

class SetupMovimientosTable extends Command
{
    protected $signature = 'movimientos:setup-table';

    protected $description = 'Crea la tabla DynamoDB de movimientos si no existe (idempotente)';

    public function handle(DynamoDbClient $client): int
    {
        $table = config('services.movimientos.table');

        try {
            $client->describeTable(['TableName' => $table]);
            $this->info("La tabla [{$table}] ya existe.");

            return self::SUCCESS;
        } catch (DynamoDbException $e) {
            if ($e->getAwsErrorCode() !== 'ResourceNotFoundException') {
                throw $e;
            }
        }

        $client->createTable([
            'TableName' => $table,
            'AttributeDefinitions' => [
                ['AttributeName' => 'cuenta_id', 'AttributeType' => 'S'],
                ['AttributeName' => 'sort_key', 'AttributeType' => 'S'],
            ],
            'KeySchema' => [
                ['AttributeName' => 'cuenta_id', 'KeyType' => 'HASH'],
                ['AttributeName' => 'sort_key', 'KeyType' => 'RANGE'],
            ],
            'BillingMode' => 'PAY_PER_REQUEST',
        ]);

        $client->waitUntil('TableExists', ['TableName' => $table]);

        $this->info("Tabla [{$table}] creada.");

        return self::SUCCESS;
    }
}
