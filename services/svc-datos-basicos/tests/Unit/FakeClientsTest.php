<?php

namespace Tests\Unit;

use App\Clients\FakeClienteComplementarioClient;
use App\Clients\FakeCoreBancarioClient;
use App\Contracts\ClienteNoEncontradoException;
use PHPUnit\Framework\TestCase;

class FakeClientsTest extends TestCase
{
    public function test_fake_core_bancario_devuelve_datos_de_un_cliente_conocido(): void
    {
        $cliente = (new FakeCoreBancarioClient())->getDatosBasicos('1001');

        $this->assertSame('Ana Torres', $cliente['nombre']);
        $this->assertNotEmpty($cliente['productos']);
    }

    public function test_fake_core_bancario_lanza_excepcion_para_un_cliente_desconocido(): void
    {
        $this->expectException(ClienteNoEncontradoException::class);
        (new FakeCoreBancarioClient())->getDatosBasicos('9999');
    }

    public function test_fake_complementario_devuelve_detalle_de_un_cliente_conocido(): void
    {
        $detalle = (new FakeClienteComplementarioClient())->getDetalle('1002');

        $this->assertSame('estandar', $detalle['segmento']);
    }

    public function test_fake_complementario_lanza_excepcion_para_un_cliente_desconocido(): void
    {
        $this->expectException(ClienteNoEncontradoException::class);
        (new FakeClienteComplementarioClient())->getDetalle('9999');
    }
}
