<?php

namespace BP\Common\Health;

use BP\Common\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * GET /health reutilizable: cualquier servicio que instale bp-common lo
 * obtiene gratis (registrado por BpCommonServiceProvider), sin tener que
 * declarar su propia ruta ni controlador.
 */
class HealthCheckController
{
    public function __invoke(): JsonResponse
    {
        return ApiResponse::success([
            'status' => 'ok',
            'service' => config('app.name', 'unknown'),
            'time' => now()->toIso8601String(),
        ]);
    }
}
