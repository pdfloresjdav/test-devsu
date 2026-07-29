<?php

namespace App\Repositories;

use App\Contracts\MovementsRepository;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Illuminate\Support\Str;

/**
 * Persists the movement history in DynamoDB. The client
 * (Aws\DynamoDb\DynamoDbClient) is configured by bp-common from
 * bp-common.dynamodb.* -- LocalStack in development, real AWS in
 * production, same code in both cases.
 *
 * Key: account_id (partition) + sort_key (range, "date#uuid" so movements
 * can be sorted chronologically and collisions between movements on the
 * same date are avoided).
 */
class DynamoDbMovementsRepository implements MovementsRepository
{
    public function __construct(
        private readonly DynamoDbClient $client,
        private readonly Marshaler $marshaler,
        private readonly string $table,
    ) {}

    public function list(string $accountId, int $limit = 20): array
    {
        $result = $this->client->query([
            'TableName' => $this->table,
            'KeyConditionExpression' => 'account_id = :accountId',
            'ExpressionAttributeValues' => $this->marshaler->marshalItem([
                ':accountId' => $accountId,
            ]),
            'ScanIndexForward' => false,
            'Limit' => $limit,
        ]);

        return array_map(
            fn (array $item) => $this->unmarshalMovement($item),
            $result['Items'] ?? [],
        );
    }

    public function register(string $accountId, string $type, float $amount, string $description): array
    {
        $date = now()->toIso8601ZuluString('microsecond');
        $movementId = (string) Str::uuid();

        $item = [
            'account_id' => $accountId,
            'sort_key' => "{$date}#{$movementId}",
            'movement_id' => $movementId,
            'type' => $type,
            'amount' => $amount,
            'description' => $description,
            'date' => $date,
        ];

        $this->client->putItem([
            'TableName' => $this->table,
            'Item' => $this->marshaler->marshalItem($item),
        ]);

        unset($item['sort_key']);

        return $item;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function unmarshalMovement(array $item): array
    {
        $data = $this->marshaler->unmarshalItem($item);
        unset($data['sort_key']);

        // The Marshaler deserializes a DynamoDB Number as int when it has
        // no decimal part (e.g. "30" -> 30 instead of 30.0); it's forced
        // to float so the output contract is always consistent.
        $data['amount'] = (float) $data['amount'];

        return $data;
    }
}
