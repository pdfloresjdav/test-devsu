<?php

namespace Tests\Unit;

use App\Channels\LogNotificationChannel;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LogNotificationChannelTest extends TestCase
{
    public function test_deja_el_canal_destinatario_y_contenido_en_el_log(): void
    {
        Log::spy();

        (new LogNotificationChannel('push'))->send('user-abc', 'Asunto de prueba', 'Cuerpo de prueba');

        Log::shouldHaveReceived('info')->once()->withArgs(function (string $mensaje, array $contexto) {
            return $contexto['canal'] === 'push'
                && $contexto['destinatario'] === 'user-abc'
                && $contexto['subject'] === 'Asunto de prueba'
                && $contexto['body'] === 'Cuerpo de prueba';
        });
    }
}
