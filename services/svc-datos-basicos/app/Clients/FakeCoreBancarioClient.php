<?php

namespace App\Clients;

use App\Contracts\ClienteNoEncontradoException;
use App\Contracts\CoreBancarioClient;

/**
 * Fixture en memoria que simula al Core Bancario mientras no exista una
 * integracion real. Se reemplaza por HttpCoreBancarioClient cambiando
 * CORE_BANCARIO_DRIVER=http en el .env, sin tocar el resto del dominio.
 */
class FakeCoreBancarioClient implements CoreBancarioClient
{
    /** @var array<string, array<string, mixed>> */
    private array $clientes = [
        '1001' => [
            'cliente_id' => '1001',
            'nombre' => 'Ana Torres',
            'documento' => '1234567890',
            'productos' => [
                ['tipo' => 'cuenta_ahorros', 'numero' => '0011-2233', 'estado' => 'activo'],
                ['tipo' => 'tarjeta_debito', 'numero' => '4455-6677', 'estado' => 'activo'],
            ],
        ],
        '1002' => [
            'cliente_id' => '1002',
            'nombre' => 'Luis Ramirez',
            'documento' => '0987654321',
            'productos' => [
                ['tipo' => 'cuenta_corriente', 'numero' => '9988-7766', 'estado' => 'activo'],
            ],
        ],
    ];

    public function getDatosBasicos(string $clienteId): array
    {
        if (! isset($this->clientes[$clienteId])) {
            throw new ClienteNoEncontradoException("El Core Bancario no tiene registrado al cliente [{$clienteId}].");
        }

        return $this->clientes[$clienteId];
    }
}
