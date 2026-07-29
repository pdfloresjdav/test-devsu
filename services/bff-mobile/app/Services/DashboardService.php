<?php

namespace App\Services;

use BP\Common\Clients\DatosBasicosClient;
use BP\Common\Clients\MovimientosClient;

class DashboardService
{
    public function __construct(
        private readonly DatosBasicosClient $datosBasicos,
        private readonly MovimientosClient $movimientos,
    ) {
    }

    /**
     * @return array{cliente: array<string, mixed>, movimientos_recientes: array<int, array<string, mixed>>}
     */
    public function obtener(string $cuentaId, string $bearerToken): array
    {
        return [
            'cliente' => $this->datosBasicos->obtenerCliente($cuentaId, $bearerToken),
            'movimientos_recientes' => $this->movimientos->listar($cuentaId, $bearerToken, limit: 10),
        ];
    }
}
