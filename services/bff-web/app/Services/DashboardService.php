<?php

namespace App\Services;

use App\Contracts\DatosBasicosClient;
use App\Contracts\MovimientosClient;

/**
 * Unico endpoint del BFF que realmente COMPONE datos de mas de un
 * servicio de negocio en un solo contrato para la SPA (los otros dos
 * endpoints son pass-through adaptado). Si cualquiera de las dos llamadas
 * falla, la falla se propaga tal cual -- no tiene sentido mostrar un
 * dashboard a medias con el saldo/cliente pero sin poder confirmar que el
 * historico de movimientos es correcto, o viceversa.
 */
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
