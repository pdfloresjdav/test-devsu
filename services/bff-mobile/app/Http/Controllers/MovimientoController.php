<?php

namespace App\Http\Controllers;

use BP\Common\Auth\JwtClaims;
use BP\Common\Clients\MovimientosClient;
use BP\Common\Clients\UpstreamServiceException;
use BP\Common\Http\ApiResponse;
use BP\Common\Http\HandlesUpstreamErrors;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MovimientoController extends Controller
{
    use HandlesUpstreamErrors;

    public function __construct(private readonly MovimientosClient $movimientos)
    {
    }

    public function index(string $cuentaId, Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 20);

        try {
            $movimientos = $this->movimientos->listar($cuentaId, JwtClaims::bearerToken($request), $limit);
        } catch (UpstreamServiceException $e) {
            return $this->upstreamError($e);
        }

        return ApiResponse::success($movimientos);
    }
}
