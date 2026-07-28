<?php

namespace App\Repositories;

use App\Contracts\MovimientosRepository;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Illuminate\Support\Str;

/**
 * Persiste el historico de movimientos en DynamoDB. El cliente
 * (Aws\DynamoDb\DynamoDbClient) lo configura bp-common a partir de
 * bp-common.dynamodb.* -- localstack en desarrollo, AWS real en produccion,
 * mismo codigo en ambos casos.
 *
 * Clave: cuenta_id (partition) + sort_key (range, "fecha#uuid" para poder
 * ordenar cronologicamente y evitar colisiones entre movimientos con la
 * misma fecha).
 */
class DynamoDbMovimientosRepository implements MovimientosRepository
{
    public function __construct(
        private readonly DynamoDbClient $client,
        private readonly Marshaler $marshaler,
        private readonly string $table,
    ) {
    }

    public function listar(string $cuentaId, int $limit = 20): array
    {
        $result = $this->client->query([
            'TableName' => $this->table,
            'KeyConditionExpression' => 'cuenta_id = :cuentaId',
            'ExpressionAttributeValues' => $this->marshaler->marshalItem([
                ':cuentaId' => $cuentaId,
            ]),
            'ScanIndexForward' => false,
            'Limit' => $limit,
        ]);

        return array_map(
            fn (array $item) => $this->unmarshalMovimiento($item),
            $result['Items'] ?? [],
        );
    }

    public function registrar(string $cuentaId, string $tipo, float $monto, string $descripcion): array
    {
        $fecha = now()->toIso8601ZuluString('microsecond');
        $movimientoId = (string) Str::uuid();

        $item = [
            'cuenta_id' => $cuentaId,
            'sort_key' => "{$fecha}#{$movimientoId}",
            'movimiento_id' => $movimientoId,
            'tipo' => $tipo,
            'monto' => $monto,
            'descripcion' => $descripcion,
            'fecha' => $fecha,
        ];

        $this->client->putItem([
            'TableName' => $this->table,
            'Item' => $this->marshaler->marshalItem($item),
        ]);

        unset($item['sort_key']);

        return $item;
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array<string, mixed>
     */
    private function unmarshalMovimiento(array $item): array
    {
        $data = $this->marshaler->unmarshalItem($item);
        unset($data['sort_key']);

        // El Marshaler deserializa un Number de DynamoDB como int cuando no
        // tiene parte decimal (ej. "30" -> 30 en vez de 30.0); se fuerza a
        // float para que el contrato de salida sea consistente siempre.
        $data['monto'] = (float) $data['monto'];

        return $data;
    }
}
