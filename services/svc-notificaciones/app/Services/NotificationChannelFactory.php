<?php

namespace App\Services;

use App\Channels\LogNotificationChannel;
use App\Channels\PinpointNotificationChannel;
use App\Channels\SesNotificationChannel;
use App\Contracts\NotificationChannel;
use Aws\Pinpoint\PinpointClient;
use Aws\Ses\SesClient;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Resuelve el adaptador de canal segun NOTIFICATION_DRIVER: 'log' (dev,
 * demuestra el flujo sin proveedores reales) o 'aws' (Pinpoint para
 * push/sms, SES para email). Cambiar el driver es solo configuracion.
 */
class NotificationChannelFactory
{
    public function __construct(private readonly Container $app)
    {
    }

    public function make(string $canal): NotificationChannel
    {
        if (config('services.notificaciones.driver') !== 'aws') {
            return new LogNotificationChannel($canal);
        }

        return match ($canal) {
            'push', 'sms' => new PinpointNotificationChannel(
                $this->app->make(PinpointClient::class),
                config('services.notificaciones.pinpoint_application_id'),
                $canal === 'push' ? 'GCM' : 'SMS',
            ),
            'email' => new SesNotificationChannel(
                $this->app->make(SesClient::class),
                config('services.notificaciones.ses_from_address'),
            ),
            default => throw new InvalidArgumentException("Canal desconocido: [{$canal}]"),
        };
    }
}
