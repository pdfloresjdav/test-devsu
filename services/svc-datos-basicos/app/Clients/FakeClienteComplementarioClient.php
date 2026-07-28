<?php

namespace App\Clients;

use App\Contracts\ClienteComplementarioClient;
use App\Contracts\ClienteNoEncontradoException;

/**
 * Fixture en memoria que simula al Sistema Complementario de Cliente. Se
 * reemplaza por HttpClienteComplementarioClient cambiando
 * CLIENTE_COMPLEMENTARIO_DRIVER=http en el .env.
 */
class FakeClienteComplementarioClient implements ClienteComplementarioClient
{
    /** @var array<string, array<string, mixed>> */
    private array $detalles = [
        '1001' => [
            'cliente_id' => '1001',
            'segmento' => 'preferente',
            'email' => 'ana.torres@example.com',
            'telefono' => '+57 300 111 2233',
            'preferencias' => ['idioma' => 'es', 'canal_notificacion' => 'push'],
        ],
        '1002' => [
            'cliente_id' => '1002',
            'segmento' => 'estandar',
            'email' => 'luis.ramirez@example.com',
            'telefono' => '+57 300 444 5566',
            'preferencias' => ['idioma' => 'es', 'canal_notificacion' => 'email'],
        ],
    ];

    public function getDetalle(string $clienteId): array
    {
        if (! isset($this->detalles[$clienteId])) {
            throw new ClienteNoEncontradoException("El Sistema Complementario no tiene registrado al cliente [{$clienteId}].");
        }

        return $this->detalles[$clienteId];
    }
}
