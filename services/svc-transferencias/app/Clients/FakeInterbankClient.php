<?php

namespace App\Clients;

use App\Contracts\InterbankClient;
use App\Contracts\InterbankException;
use Illuminate\Support\Str;

/**
 * Simula la red interbancaria mientras no exista una integracion real.
 * Determinista para poder probar el camino de compensacion: cualquier
 * cuenta destino que empiece con "FALLA-" es rechazada por el banco
 * destino; el resto se confirma.
 */
class FakeInterbankClient implements InterbankClient
{
    public function ejecutar(string $cuentaDestino, float $monto): array
    {
        if (str_starts_with($cuentaDestino, 'FALLA-')) {
            throw new InterbankException("El banco destino rechazo la transferencia hacia [{$cuentaDestino}].");
        }

        return ['confirmacion_id' => (string) Str::uuid()];
    }
}
