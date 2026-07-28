<?php

namespace App\Http\Controllers;

use App\Contracts\ClienteNoEncontradoException;
use App\Services\ClienteCompositionService;
use BP\Common\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class ClienteController extends Controller
{
    public function __construct(private readonly ClienteCompositionService $composition)
    {
    }

    public function show(string $clienteId): JsonResponse
    {
        try {
            $cliente = $this->composition->componer($clienteId);
        } catch (ClienteNoEncontradoException $e) {
            return ApiResponse::error($e->getMessage(), 'cliente_no_encontrado', status: 404);
        }

        return ApiResponse::success($cliente);
    }
}
