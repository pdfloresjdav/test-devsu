<?php

namespace App\Repositories;

use App\Contracts\MovimientosRepository;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Patron Cache-Aside (decision 3.8): en lectura, sirve desde Redis si esta
 * disponible y solo pega a DynamoDB en un miss; en escritura, invalida
 * activamente la cache de la cuenta (via tags) en vez de esperar a que
 * expire el TTL -- evita servir un historico desactualizado justo despues
 * de registrar un movimiento nuevo.
 */
class CachedMovimientosRepository implements MovimientosRepository
{
    public function __construct(
        private readonly MovimientosRepository $inner,
        private readonly CacheRepository $cache,
        private readonly int $ttlSeconds,
    ) {
    }

    public function listar(string $cuentaId, int $limit = 20): array
    {
        return $this->cache
            ->tags($this->tagFor($cuentaId))
            ->remember(
                $this->cacheKeyFor($cuentaId, $limit),
                $this->ttlSeconds,
                fn () => $this->inner->listar($cuentaId, $limit),
            );
    }

    public function registrar(string $cuentaId, string $tipo, float $monto, string $descripcion): array
    {
        $movimiento = $this->inner->registrar($cuentaId, $tipo, $monto, $descripcion);

        $this->cache->tags($this->tagFor($cuentaId))->flush();

        return $movimiento;
    }

    /**
     * @return array<int, string>
     */
    private function tagFor(string $cuentaId): array
    {
        return ["movimientos:{$cuentaId}"];
    }

    private function cacheKeyFor(string $cuentaId, int $limit): string
    {
        return "movimientos:{$cuentaId}:{$limit}";
    }
}
