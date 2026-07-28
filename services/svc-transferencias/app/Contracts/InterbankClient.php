<?php

namespace App\Contracts;

interface InterbankClient
{
    /**
     * Ejecuta la transferencia en la red interbancaria / banco destino.
     *
     * @return array{confirmacion_id: string}
     *
     * @throws InterbankException si el banco destino rechaza o no responde.
     */
    public function ejecutar(string $cuentaDestino, float $monto): array;
}
