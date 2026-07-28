<?php

namespace App\Repositories;

use App\Contracts\AuditRepository;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Illuminate\Support\Str;

/**
 * Persiste el rastro de auditoria en DynamoDB (LocalStack en local, AWS
 * real en produccion -- mismo codigo, mismo patron que el resto del
 * proyecto). Clave: actor (partition) + sort_key = "timestamp#audit_id"
 * (range), para poder consultar el historial de un actor en orden
 * cronologico si en el futuro se agrega un endpoint de consulta.
 */
class DynamoDbAuditRepository implements AuditRepository
{
    public function __construct(
        private readonly DynamoDbClient $client,
        private readonly Marshaler $marshaler,
        private readonly string $table,
    ) {
    }

    public function registrar(string $actor, string $accion, array $detalle): array
    {
        $timestamp = now()->toIso8601ZuluString('microsecond');
        $auditId = (string) Str::uuid();
        $hash = $this->calcularHash($actor, $accion, $detalle, $timestamp);

        $item = [
            'actor' => $actor,
            'sort_key' => "{$timestamp}#{$auditId}",
            'audit_id' => $auditId,
            'accion' => $accion,
            'detalle' => $detalle,
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
     * Hash de integridad del registro (no es un mecanismo de inmutabilidad
     * por si solo -- eso lo da el WORM Archiver en S3 Object Lock en AWS
     * real -- sino evidencia de que el contenido no fue alterado despues
     * de escrito).
     *
     * @param array<string, mixed> $detalle
     */
    private function calcularHash(string $actor, string $accion, array $detalle, string $timestamp): string
    {
        $canonico = json_encode([$actor, $accion, $detalle, $timestamp], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return hash('sha256', $canonico);
    }
}
