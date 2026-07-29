<?php

namespace App\Http\Controllers;

use App\Contracts\LivenessProvider;
use BP\Common\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Revalidacion de riesgo para operaciones sensibles (diagrama de secuencia
 * 8.2). A diferencia del onboarding, aqui SI hay un usuario autenticado
 * (esta operando dentro de la app), asi que la ruta esta protegida con
 * JwtAuthMiddleware -- se mediatiza a traves del BFF en vez de que la app
 * llame a AWS Rekognition directo, para no distribuir credenciales de AWS
 * en el cliente movil.
 */
class LivenessController extends Controller
{
    public function __construct(private readonly LivenessProvider $liveness)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'selfie_referencia' => ['required', 'string'],
            'selfie_nueva' => ['required', 'string'],
        ]);

        $resultado = $this->liveness->revalidar($validated['selfie_referencia'], $validated['selfie_nueva']);

        return ApiResponse::success($resultado);
    }
}
