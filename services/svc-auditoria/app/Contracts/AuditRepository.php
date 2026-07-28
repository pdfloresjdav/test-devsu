<?php

namespace App\Contracts;

interface AuditRepository
{
    /**
     * Persiste un registro de auditoria inmutable (actor, accion, detalle,
     * timestamp y hash del evento) -- decision "Base de datos de auditoria"
     * del documento de arquitectura.
     *
     * @param array<string, mixed> $detalle
     *
     * @return array{
     *     audit_id: string,
     *     actor: string,
     *     accion: string,
     *     detalle: array<string, mixed>,
     *     hash: string,
     *     timestamp: string
     * }
     */
    public function registrar(string $actor, string $accion, array $detalle): array;
}
