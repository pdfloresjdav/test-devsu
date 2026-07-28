<?php

namespace App\Contracts;

interface CoreBancarioClient
{
    /**
     * Datos basicos del cliente, sus productos y un resumen de movimientos,
     * tal como los expone el Core Bancario (sistema de registro oficial).
     *
     * @return array{
     *     cliente_id: string,
     *     nombre: string,
     *     documento: string,
     *     productos: array<int, array{tipo: string, numero: string, estado: string}>
     * }
     */
    public function getDatosBasicos(string $clienteId): array;
}
