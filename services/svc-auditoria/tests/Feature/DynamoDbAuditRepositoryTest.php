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
 * Corre contra el LocalStack real de docker-compose (bp-localstack), igual
 * que el resto de los servicios del proyecto.
 */
class DynamoDbAuditRepositoryTest extends TestCase
{
    public function test_registra_un_registro_de_auditoria_con_hash_y_timestamp(): void
    {
        $actor = 'actor-' . Str::uuid();

        $repository = new DynamoDbAuditRepository(
            $this->app->make(DynamoDbClient::class),
            $this->app->make(Marshaler::class),
            config('services.auditoria.table'),
        );

        $registro = $repository->registrar($actor, 'MovementRegistered', ['cuenta_id' => 'X', 'monto' => 10]);

        $this->assertSame($actor, $registro['actor']);
        $this->assertSame('MovementRegistered', $registro['accion']);
        $this->assertNotEmpty($registro['hash']);
        $this->assertNotEmpty($registro['timestamp']);
        $this->assertNotEmpty($registro['audit_id']);
    }

    public function test_el_worm_archiver_escribe_el_registro_en_s3(): void
    {
        $archiver = new WormArchiver(
            $this->app->make(S3Client::class),
            config('services.auditoria.bucket'),
        );

        $registro = [
            'audit_id' => (string) Str::uuid(),
            'actor' => 'actor-worm-test',
            'accion' => 'MovementRegistered',
            'detalle' => ['x' => 1],
            'hash' => 'abc123',
            'timestamp' => now()->toIso8601String(),
        ];

        $archiver->archivar($registro);

        $s3 = $this->app->make(S3Client::class);
        $objeto = $s3->getObject([
            'Bucket' => config('services.auditoria.bucket'),
            'Key' => "auditoria/{$registro['actor']}/{$registro['audit_id']}.json",
        ]);

        $contenido = json_decode((string) $objeto['Body'], true);
        $this->assertSame($registro['audit_id'], $contenido['audit_id']);
        $this->assertSame('abc123', $contenido['hash']);
    }
}
