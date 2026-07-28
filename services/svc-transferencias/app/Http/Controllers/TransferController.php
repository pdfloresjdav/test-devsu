<?php

namespace App\Http\Controllers;

use App\Contracts\SaldoInsuficienteException;
use App\Services\TransferOrchestrator;
use BP\Common\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransferController extends Controller
{
    public function __construct(private readonly TransferOrchestrator $orchestrator)
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

        $idempotencyKey = $request->attributes->get('idempotency_key');

        try {
            $transferencia = $this->orchestrator->ejecutar(
                $validated['cuenta_origen'],
                $validated['cuenta_destino'],
                (float) $validated['monto'],
                $validated['descripcion'] ?? '',
                $idempotencyKey,
            );
        } catch (SaldoInsuficienteException $e) {
            return ApiResponse::error($e->getMessage(), 'saldo_insuficiente', status: 422);
        }

        return ApiResponse::success([
            'transferencia_id' => $transferencia->transferencia_id,
            'cuenta_origen' => $transferencia->cuenta_origen,
            'cuenta_destino' => $transferencia->cuenta_destino,
            'monto' => (float) $transferencia->monto,
            'descripcion' => $transferencia->descripcion,
            'estado' => $transferencia->estado,
            'motivo_falla' => $transferencia->motivo_falla,
        ], status: 201);
    }
}
