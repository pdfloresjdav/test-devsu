<?php

namespace Tests\Unit;

use App\Contracts\DeliveryTracker;
use App\Contracts\NotificationChannel;
use App\Contracts\NotificationDeliveryException;
use App\Services\ChannelRouter;
use App\Services\NotificationChannelFactory;
use App\Services\NotificationEventProcessor;
use App\Services\TemplateEngine;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class NotificationEventProcessorTest extends TestCase
{
    public function test_envia_por_cada_canal_y_registra_el_exito(): void
    {
        $router = $this->createMock(ChannelRouter::class);
        $router->method('canalesPara')->with('TransferFailed')->willReturn(['push', 'email']);

        $templates = $this->createMock(TemplateEngine::class);
        $templates->method('render')->willReturn(['subject' => 'Asunto', 'body' => 'Cuerpo']);

        $channel = $this->createMock(NotificationChannel::class);
        $channel->expects($this->exactly(2))->method('send')->with('user-abc', 'Asunto', 'Cuerpo');

        $factory = $this->createMock(NotificationChannelFactory::class);
        $factory->method('make')->willReturn($channel);

        $tracker = $this->createMock(DeliveryTracker::class);
        $tracker->expects($this->exactly(2))->method('registrar')->with('user-abc', $this->isType('string'), 'TransferFailed', 'enviado');

        $processor = new NotificationEventProcessor($router, $templates, $factory, $tracker);

        $processor->procesar([
            'detail-type' => 'TransferFailed',
            'detail' => ['actor' => 'user-abc', 'monto' => 100],
        ]);
    }

    public function test_registra_fallido_si_el_canal_no_pudo_entregar(): void
    {
        $router = $this->createMock(ChannelRouter::class);
        $router->method('canalesPara')->willReturn(['push']);

        $templates = $this->createMock(TemplateEngine::class);
        $templates->method('render')->willReturn(['subject' => 'Asunto', 'body' => 'Cuerpo']);

        $channel = $this->createMock(NotificationChannel::class);
        $channel->method('send')->willThrowException(new NotificationDeliveryException('proveedor caido'));

        $factory = $this->createMock(NotificationChannelFactory::class);
        $factory->method('make')->willReturn($channel);

        $tracker = $this->createMock(DeliveryTracker::class);
        $tracker->expects($this->once())->method('registrar')->with('user-abc', 'push', 'MovementRegistered', 'fallido', 'proveedor caido');

        $processor = new NotificationEventProcessor($router, $templates, $factory, $tracker);

        $processor->procesar([
            'detail-type' => 'MovementRegistered',
            'detail' => ['actor' => 'user-abc'],
        ]);
    }

    public function test_rechaza_un_evento_sin_detail(): void
    {
        $processor = new NotificationEventProcessor(
            $this->createMock(ChannelRouter::class),
            $this->createMock(TemplateEngine::class),
            $this->createMock(NotificationChannelFactory::class),
            $this->createMock(DeliveryTracker::class),
        );

        $this->expectException(RuntimeException::class);
        $processor->procesar(['detail-type' => 'MovementRegistered']);
    }
}
