<?php

namespace Tests\Feature;

use App\Repositories\DynamoDbMovimientosRepository;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Corre contra el LocalStack real de docker-compose (bp-localstack),
 * siguiendo la convencion del proyecto de probar contra la infraestructura
 * local en vez de mockear el SDK de AWS.
 */
class DynamoDbMovimientosRepositoryTest extends TestCase
{
    public function test_registra_y_lista_movimientos_en_orden_cronologico_descendente(): void
    {
        $repo = new DynamoDbMovimientosRepository(
            $this->app->make(DynamoDbClient::class),
            $this->app->make(Marshaler::class),
            config('services.movimientos.table'),
        );

        $cuentaId = (string) Str::uuid();

        $primero = $repo->registrar($cuentaId, 'credito', 100.0, 'deposito inicial');
        usleep(2000);
        $segundo = $repo->registrar($cuentaId, 'debito', 30.0, 'compra');

        $movimientos = $repo->listar($cuentaId);

        $this->assertCount(2, $movimientos);
        $this->assertSame($segundo['movimiento_id'], $movimientos[0]['movimiento_id'], 'El mas reciente debe venir primero');
        $this->assertSame($primero['movimiento_id'], $movimientos[1]['movimiento_id']);
        $this->assertSame(30.0, $movimientos[0]['monto']);
    }

    public function test_respeta_el_limite_solicitado(): void
    {
        $repo = new DynamoDbMovimientosRepository(
            $this->app->make(DynamoDbClient::class),
            $this->app->make(Marshaler::class),
            config('services.movimientos.table'),
        );

        $cuentaId = (string) Str::uuid();

        foreach (range(1, 5) as $i) {
            $repo->registrar($cuentaId, 'debito', (float) $i, "movimiento {$i}");
        }

        $movimientos = $repo->listar($cuentaId, limit: 2);

        $this->assertCount(2, $movimientos);
    }
}
