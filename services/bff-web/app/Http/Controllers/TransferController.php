<?php

namespace App\Http\Controllers;

use App\Contracts\TransferenciasClient;
use App\Contracts\UpstreamServiceException;
use BP\Common\Auth\JwtClaims;
use BP\Common\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransferController extends Controller
{
    use HandlesUpstreamErrors;

    public function __construct(private readonly TransferenciasClient $transferencias)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cuenta_origen' => ['required', 'string'],
            'cuenta_destino' => ['required', 'string'],
            'monto' => ['required', 'numeric', 'gt:0'],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ]);

        $idempotencyKey = $request->header('Idempotency-Key');

        if (! $idempotencyKey) {
            return ApiResponse::error('Falta el header Idempotency-Key.', 'missing_idempotency_key', status: 400);
        }

        try {
            $resultado = $this->transferencias->crear($validated, $idempotencyKey, JwtClaims::bearerToken($request));
        } catch (UpstreamServiceException $e) {
            return $this->upstreamError($e);
        }

        return ApiResponse::success($resultado, status: 201);
    }
}
