<?php

namespace App\Http\Controllers;

use App\Contracts\MovimientosRepository;
use BP\Common\Events\EventPublisherInterface;
use BP\Common\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MovimientoController extends Controller
{
    public function __construct(
        private readonly MovimientosRepository $repository,
        private readonly EventPublisherInterface $events,
    ) {
    }

    public function index(string $cuentaId, Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 20);

        return ApiResponse::success($this->repository->listar($cuentaId, $limit));
    }

    public function store(string $cuentaId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tipo' => ['required', 'string', 'in:debito,credito'],
            'monto' => ['required', 'numeric', 'gt:0'],
            'descripcion' => ['required', 'string', 'max:255'],
        ]);

        $movimiento = $this->repository->registrar(
            $cuentaId,
            $validated['tipo'],
            (float) $validated['monto'],
            $validated['descripcion'],
        );

        $this->events->publish('MovementRegistered', $movimiento);

        return ApiResponse::success($movimiento, status: 201);
    }
}
