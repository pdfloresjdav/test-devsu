<?php

namespace Tests\Unit;

use App\Contracts\ClienteComplementarioClient;
use App\Contracts\CoreBancarioClient;
use App\Services\ClienteCompositionService;
use PHPUnit\Framework\TestCase;

class ClienteCompositionServiceTest extends TestCase
{
    public function test_compone_los_datos_del_core_y_el_complementario_en_un_solo_contrato(): void
    {
        $core = $this->createMock(CoreBancarioClient::class);
        $core->method('getDatosBasicos')->with('1001')->willReturn([
            'cliente_id' => '1001',
            'nombre' => 'Ana Torres',
            'documento' => '1234567890',
            'productos' => [['tipo' => 'cuenta_ahorros', 'numero' => '0011', 'estado' => 'activo']],
        ]);

        $complementario = $this->createMock(ClienteComplementarioClient::class);
        $complementario->method('getDetalle')->with('1001')->willReturn([
            'cliente_id' => '1001',
            'segmento' => 'preferente',
            'email' => 'ana@example.com',
            'telefono' => '+57 300 000 0000',
            'preferencias' => ['idioma' => 'es'],
        ]);

        $service = new ClienteCompositionService($core, $complementario);
        $resultado = $service->componer('1001');

        $this->assertSame('1001', $resultado['cliente_id']);
        $this->assertSame('Ana Torres', $resultado['nombre']);
        $this->assertSame('preferente', $resultado['segmento']);
        $this->assertSame('ana@example.com', $resultado['contacto']['email']);
        $this->assertSame(['idioma' => 'es'], $resultado['preferencias']);
    }
}
