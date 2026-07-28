<?php

namespace App\Services;

/**
 * Decide por que canal(es) notificar segun el tipo de evento (decision
 * 3.12: separar el canal inmediato -push- del canal de respaldo -email-
 * para dar redundancia real). El mapeo vive en config para poder ajustarlo
 * sin tocar codigo.
 */
class ChannelRouter
{
    /**
     * @return array<int, string>
     */
    public function canalesPara(string $accion): array
    {
        $mapa = config('services.notificaciones.channel_map', []);

        return $mapa[$accion] ?? $mapa['default'] ?? ['push'];
    }
}
