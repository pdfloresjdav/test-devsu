<?php

namespace App\Services;

use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

/**
 * Genera el contenido de la notificacion segun el tipo de evento (y, en el
 * futuro, el idioma del cliente -- por ahora solo 'es', ver limitacion en
 * el .env.example). Usa vistas Blade en resources/views/notifications/,
 * una por tipo de evento, con un fallback generico.
 */
class TemplateEngine
{
    /**
     * @param array<string, mixed> $detalle
     *
     * @return array{subject: string, body: string}
     */
    public function render(string $accion, array $detalle, string $locale = 'es'): array
    {
        $vista = 'notifications.' . Str::kebab($accion);

        if (! View::exists($vista)) {
            $vista = 'notifications.default';
        }

        $subject = config("services.notificaciones.subject_map.{$accion}", 'Notificación BP');
        $body = trim(View::make($vista, ['detalle' => $detalle, 'accion' => $accion])->render());

        return ['subject' => $subject, 'body' => $body];
    }
}
