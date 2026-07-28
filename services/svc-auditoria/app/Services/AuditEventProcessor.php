<?php

namespace App\Services;

use App\Contracts\AuditRepository;
use RuntimeException;

/**
 * Traduce un evento de dominio (el "detail" de un evento de EventBridge) en
 * un registro de auditoria, lo persiste y lo archiva. Separado del
 * consumidor de SQS para poder probarlo sin depender de una cola real.
 */
class AuditEventProcessor
{
    public function __construct(
        private readonly AuditRepository $repository,
        private readonly WormArchiver $archiver,
    ) {
    }

    /**
     * @param array<string, mixed> $eventBridgeEvent Evento completo tal como lo entrega EventBridge/SQS.
     */
    public function procesar(array $eventBridgeEvent): void
    {
        $accion = $eventBridgeEvent['detail-type'] ?? null;
        $detalle = $eventBridgeEvent['detail'] ?? null;

        if (! is_string($accion) || ! is_array($detalle)) {
            throw new RuntimeException('Evento invalido: falta detail-type o detail.');
        }

        $actor = $detalle['actor'] ?? 'system';

        $registro = $this->repository->registrar($actor, $accion, $detalle);

        $this->archiver->archivar($registro);
    }
}
