<?php

namespace App\Contracts;

interface MovimientosRepository
{
    /**
     * Historico de movimientos de una cuenta, mas recientes primero.
     *
     * @return array<int, array{
     *     movimiento_id: string,
     *     cuenta_id: string,
     *     tipo: string,
     *     monto: float,
     *     descripcion: string,
     *     fecha: string
     * }>
     */
    public function listar(string $cuentaId, int $limit = 20): array;

    /**
     * Registra un nuevo movimiento y devuelve el registro completo
     * (incluyendo el id y la fecha asignados).
     *
     * @return array{
     *     movimiento_id: string,
     *     cuenta_id: string,
     *     tipo: string,
     *     monto: float,
     *     descripcion: string,
     *     fecha: string
     * }
     */
    public function registrar(string $cuentaId, string $tipo, float $monto, string $descripcion): array;
}
