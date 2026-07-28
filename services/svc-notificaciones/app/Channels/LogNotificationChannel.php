<?php

namespace App\Channels;

use App\Contracts\NotificationChannel;
use Illuminate\Support\Facades\Log;

/**
 * Driver de desarrollo: en vez de enviar la notificacion de verdad, la deja
 * en el log con el canal, destinatario y contenido -- suficiente para
 * demostrar el flujo completo (decision de la Fase 6) sin depender de
 * Pinpoint/SES reales ni de soporte de Pinpoint en LocalStack (no lo
 * tiene). Cambiar a NOTIFICATION_DRIVER=aws en el .env activa
 * PinpointNotificationChannel/SesNotificationChannel sin tocar codigo.
 */
class LogNotificationChannel implements NotificationChannel
{
    public function __construct(private readonly string $canal)
    {
    }

    public function send(string $destinatario, string $subject, string $body): void
    {
        Log::info('Notificacion enviada (driver log)', [
            'canal' => $this->canal,
            'destinatario' => $destinatario,
            'subject' => $subject,
            'body' => $body,
        ]);
    }
}
