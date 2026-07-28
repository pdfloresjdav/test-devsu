<?php

namespace Tests\Feature;

use App\Contracts\MovimientosRepository;
use App\Repositories\CachedMovimientosRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class CachedMovimientosRepositoryTest extends TestCase
{
    private function countingInnerRepository(array &$calls): MovimientosRepository
    {
        return new class($calls) implements MovimientosRepository {
            public function __construct(private array &$calls)
            {
            }

            public function listar(string $cuentaId, int $limit = 20): array
            {
                $this->calls[] = $cuentaId;

                return [['movimiento_id' => 'm1', 'cuenta_id' => $cuentaId, 'tipo' => 'debito', 'monto' => 10.0, 'descripcion' => 'x', 'fecha' => 'ahora']];
            }

            public function registrar(string $cuentaId, string $tipo, float $monto, string $descripcion): array
            {
                return ['movimiento_id' => 'nuevo', 'cuenta_id' => $cuentaId, 'tipo' => $tipo, 'monto' => $monto, 'descripcion' => $descripcion, 'fecha' => 'ahora'];
            }
        };
    }

    public function test_la_segunda_lectura_no_vuelve_a_pegarle_al_repositorio_interno(): void
    {
        $cuentaId = (string) Str::uuid();
        $calls = [];
        $repo = new CachedMovimientosRepository($this->countingInnerRepository($calls), Cache::store('redis'), 60);

        $repo->listar($cuentaId);
        $repo->listar($cuentaId);

        $this->assertCount(1, $calls, 'El repositorio interno solo debio llamarse una vez (la segunda lectura debio venir de Redis)');
    }

    public function test_registrar_invalida_la_cache_de_la_cuenta(): void
    {
        $cuentaId = (string) Str::uuid();
        $calls = [];
        $repo = new CachedMovimientosRepository($this->countingInnerRepository($calls), Cache::store('redis'), 60);

        $repo->listar($cuentaId);
        $repo->registrar($cuentaId, 'debito', 50.0, 'compra');
        $repo->listar($cuentaId);

        $this->assertCount(2, $calls, 'Tras registrar un movimiento, la siguiente lectura debio ser un miss (cache invalidada) y volver a pegarle al repositorio interno');
    }
}
