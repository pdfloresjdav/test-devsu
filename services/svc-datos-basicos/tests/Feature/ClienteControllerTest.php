<?php

namespace Tests\Feature;

use Tests\TestCase;

class ClienteControllerTest extends TestCase
{
    public function test_rechaza_la_consulta_sin_token(): void
    {
        $this->getJson('/clientes/1001')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'missing_token');
    }

    public function test_compone_los_datos_de_un_cliente_existente(): void
    {
        $token = $this->signToken();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/clientes/1001')
            ->assertStatus(200)
            ->assertJsonPath('data.cliente_id', '1001')
            ->assertJsonPath('data.nombre', 'Ana Torres')
            ->assertJsonPath('data.segmento', 'preferente')
            ->assertJsonPath('data.contacto.email', 'ana.torres@example.com');
    }

    public function test_devuelve_404_para_un_cliente_inexistente(): void
    {
        $token = $this->signToken();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/clientes/9999')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'cliente_no_encontrado');
    }
}
