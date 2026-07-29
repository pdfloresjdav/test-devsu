<?php

namespace App\Repositories;

use App\Contracts\AuditRepository;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Illuminate\Support\Str;

/**
 * Persists the audit trail in DynamoDB (LocalStack locally, real AWS in
 * production -- same code, same pattern as the rest of the project). Key:
 * actor (partition) + sort_key = "timestamp#audit_id" (range), so an
 * actor's history can be queried in chronological order if a query
 * endpoint is added in the future.
 */
class DynamoDbAuditRepository implements AuditRepository
{
    public function __construct(
        private readonly DynamoDbClient $client,
        private readonly Marshaler $marshaler,
        private readonly string $table,
    ) {
    }

    public function register(string $actor, string $action, array $detail): array
    {
        $timestamp = now()->toIso8601ZuluString('microsecond');
        $auditId = (string) Str::uuid();
        $hash = $this->calculateHash($actor, $action, $detail, $timestamp);

        $item = [
            'actor' => $actor,
            'sort_key' => "{$timestamp}#{$auditId}",
            'audit_id' => $auditId,
            'action' => $action,
            'detail' => $detail,
            'hash' => $hash,
            'timestamp' => $timestamp,
        ];

        $this->client->putItem([
            'TableName' => $this->table,
            'Item' => $this->marshaler->marshalItem($item),
        ]);

        unset($item['sort_key']);

        return $item;
    }

    /**
     * Integrity hash of the record (it's not an immutability mechanism by
     * itself -- that's provided by the WORM Archiver via S3 Object Lock on
     * real AWS -- but evidence that the content wasn't altered after it
     * was written).
     *
     * @param array<string, mixed> $detail
     */
    private function calculateHash(string $actor, string $action, array $detail, string $timestamp): string
    {
        $canonical = json_encode([$actor, $action, $detail, $timestamp], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return hash('sha256', $canonical);
    }
}
