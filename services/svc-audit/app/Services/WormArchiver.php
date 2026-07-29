<?php

namespace App\Services;

use Aws\S3\S3Client;

/**
 * Archives each audit record in S3 as a long-term copy.
 *
 * IMPORTANT: on real AWS, the configured bucket must have Object Lock in
 * Compliance mode enabled (see decision 3.9 of the architecture document)
 * -- that's what gives real immutability (not even the account root can
 * delete the object before the retention period expires). LocalStack
 * doesn't support real Object Lock, so locally this class is only a
 * stand-in that demonstrates the flow (writing the object), without
 * guaranteeing immutability. Don't confuse "it already works locally"
 * with "it's already immutable".
 */
class WormArchiver
{
    public function __construct(
        private readonly S3Client $client,
        private readonly string $bucket,
    ) {}

    /**
     * @param  array<string, mixed>  $record
     */
    public function archive(array $record): void
    {
        $key = "audit/{$record['actor']}/{$record['audit_id']}.json";

        $this->client->putObject([
            'Bucket' => $this->bucket,
            'Key' => $key,
            'Body' => json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'ContentType' => 'application/json',
        ]);
    }
}
