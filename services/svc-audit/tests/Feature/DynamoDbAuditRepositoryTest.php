<?php

namespace Tests\Feature;

use App\Repositories\DynamoDbAuditRepository;
use App\Services\WormArchiver;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Aws\S3\S3Client;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Runs against the real LocalStack from docker-compose (bp-localstack),
 * just like the rest of the project's services.
 */
class DynamoDbAuditRepositoryTest extends TestCase
{
    public function test_registers_an_audit_record_with_hash_and_timestamp(): void
    {
        $actor = 'actor-'.Str::uuid();

        $repository = new DynamoDbAuditRepository(
            $this->app->make(DynamoDbClient::class),
            $this->app->make(Marshaler::class),
            config('services.audit.table'),
        );

        $record = $repository->register($actor, 'MovementRegistered', ['account_id' => 'X', 'amount' => 10]);

        $this->assertSame($actor, $record['actor']);
        $this->assertSame('MovementRegistered', $record['action']);
        $this->assertNotEmpty($record['hash']);
        $this->assertNotEmpty($record['timestamp']);
        $this->assertNotEmpty($record['audit_id']);
    }

    public function test_the_worm_archiver_writes_the_record_to_s3(): void
    {
        $archiver = new WormArchiver(
            $this->app->make(S3Client::class),
            config('services.audit.bucket'),
        );

        $record = [
            'audit_id' => (string) Str::uuid(),
            'actor' => 'actor-worm-test',
            'action' => 'MovementRegistered',
            'detail' => ['x' => 1],
            'hash' => 'abc123',
            'timestamp' => now()->toIso8601String(),
        ];

        $archiver->archive($record);

        $s3 = $this->app->make(S3Client::class);
        $object = $s3->getObject([
            'Bucket' => config('services.audit.bucket'),
            'Key' => "audit/{$record['actor']}/{$record['audit_id']}.json",
        ]);

        $content = json_decode((string) $object['Body'], true);
        $this->assertSame($record['audit_id'], $content['audit_id']);
        $this->assertSame('abc123', $content['hash']);
    }
}
