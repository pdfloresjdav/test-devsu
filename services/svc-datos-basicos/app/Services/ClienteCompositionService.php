<?php

namespace App\Services;

use App\Contracts\ClienteComplementarioClient;
use App\Contracts\CoreBancarioClient;

/**
 * Patron API Composition: combina la respuesta del Core Bancario y del
 * Sistema Complementario de Cliente en un unico contrato de salida, para
 * que el BFF no tenga que orquestar dos llamadas ni conocer que existen
 * dos fuentes distintas.
 */
class ClienteCompositionService
{
    public function __construct(
        private readonly CoreBancarioClient $coreBancario,
        private readonly ClienteComplementarioClient $clienteComplementario,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function componer(string $clienteId): array
    {
        $datosBasicos = $this->coreBancario->getDatosBasicos($clienteId);
        $detalle = $this->clienteComplementario->getDetalle($clienteId);

        return [
            'cliente_id' => $clienteId,
            'nombre' => $datosBasicos['nombre'],
            'documento' => $datosBasicos['documento'],
            'productos' => $datosBasicos['productos'],
            'segmento' => $detalle['segmento'],
            'contacto' => [
                'email' => $detalle['email'],
                'telefono' => $detalle['telefono'],
            ],
            'preferencias' => $detalle['preferencias'],
        ];
    }
}
