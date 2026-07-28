<?php

namespace App\Contracts;

interface DeliveryTracker
{
    /**
     * Registra el resultado del intento de entrega -- evidencia de que el
     * cliente fue notificado (o de que fallo), exigida por la norma.
     */
    public function registrar(string $actor, string $canal, string $accion, string $estado, ?string $motivoFalla = null): void;
}
