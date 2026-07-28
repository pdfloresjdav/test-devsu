<?php

namespace App\Contracts;

interface NotificationChannel
{
    /**
     * Envia la notificacion. Debe lanzar NotificationDeliveryException si
     * el envio falla, para que el DeliveryTracker registre el resultado.
     */
    public function send(string $destinatario, string $subject, string $body): void;
}
