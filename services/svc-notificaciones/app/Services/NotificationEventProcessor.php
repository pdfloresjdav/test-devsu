<?php

namespace App\Services;

use App\Contracts\DeliveryTracker;
use App\Contracts\NotificationDeliveryException;
use RuntimeException;
use Throwable;

/**
 * Traduce un evento de dominio en una o mas notificaciones: decide el(los)
 * canal(es) (ChannelRouter), genera el contenido (TemplateEngine), las
 * envia (NotificationChannel) y registra el resultado (DeliveryTracker).
 * Separado del consumidor de SQS para poder probarlo sin una cola real.
 */
class NotificationEventProcessor
{
    public function __construct(
        private readonly ChannelRouter $router,
        private readonly TemplateEngine $templates,
        private readonly NotificationChannelFactory $channels,
        private readonly DeliveryTracker $tracker,
    ) {
    }

    /**
     * @param array<string, mixed> $eventBridgeEvent
     */
    public function procesar(array $eventBridgeEvent): void
    {
        $accion = $eventBridgeEvent['detail-type'] ?? null;
        $detalle = $eventBridgeEvent['detail'] ?? null;

        if (! is_string($accion) || ! is_array($detalle)) {
            throw new RuntimeException('Evento invalido: falta detail-type o detail.');
        }

        $actor = $detalle['actor'] ?? 'system';
        ['subject' => $subject, 'body' => $body] = $this->templates->render($accion, $detalle);

        foreach ($this->router->canalesPara($accion) as $canal) {
            $this->enviarPorCanal($canal, $actor, $accion, $subject, $body);
        }
    }

    private function enviarPorCanal(string $canal, string $actor, string $accion, string $subject, string $body): void
    {
        try {
            $this->channels->make($canal)->send($actor, $subject, $body);
            $this->tracker->registrar($actor, $canal, $accion, 'enviado');
        } catch (NotificationDeliveryException $e) {
            $this->tracker->registrar($actor, $canal, $accion, 'fallido', $e->getMessage());
        }
    }
}
