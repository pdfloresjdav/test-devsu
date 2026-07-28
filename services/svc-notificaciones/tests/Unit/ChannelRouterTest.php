<?php

namespace Tests\Unit;

use App\Services\ChannelRouter;
use Tests\TestCase;

class ChannelRouterTest extends TestCase
{
    public function test_movement_registered_solo_va_por_push(): void
    {
        $this->assertSame(['push'], (new ChannelRouter())->canalesPara('MovementRegistered'));
    }

    public function test_transfer_failed_va_por_push_y_email(): void
    {
        $this->assertSame(['push', 'email'], (new ChannelRouter())->canalesPara('TransferFailed'));
    }

    public function test_un_evento_desconocido_usa_el_canal_por_defecto(): void
    {
        $this->assertSame(['push'], (new ChannelRouter())->canalesPara('EventoQueNoExiste'));
    }
}
