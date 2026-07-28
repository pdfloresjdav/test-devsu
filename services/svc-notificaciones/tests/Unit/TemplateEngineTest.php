<?php

namespace Tests\Unit;

use App\Services\TemplateEngine;
use Tests\TestCase;

class TemplateEngineTest extends TestCase
{
    public function test_genera_el_contenido_para_un_movimiento_registrado(): void
    {
        $resultado = (new TemplateEngine())->render('MovementRegistered', [
            'cuenta_id' => 'CUENTA-1',
            'tipo' => 'debito',
            'monto' => 150,
        ]);

        $this->assertSame('Nuevo movimiento en tu cuenta BP', $resultado['subject']);
        $this->assertStringContainsString('CUENTA-1', $resultado['body']);
        $this->assertStringContainsString('150.00', $resultado['body']);
    }

    public function test_usa_la_plantilla_generica_para_un_evento_sin_plantilla_propia(): void
    {
        $resultado = (new TemplateEngine())->render('EventoDesconocido', []);

        $this->assertStringContainsString('EventoDesconocido', $resultado['body']);
    }
}
