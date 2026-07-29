<?php

namespace App\Http\Controllers;

use App\Contracts\OnboardingRejectedException;
use App\Services\OnboardingService;
use BP\Common\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A proposito NO esta protegido con JwtAuthMiddleware: un cliente nuevo
 * todavia no tiene token. La verificacion KYC es el control de acceso real
 * de este endpoint. En un sistema en produccion se sumaria ademas
 * atestacion de la app / rate limiting -- fuera del alcance de esta fase.
 */
class OnboardingController extends Controller
{
    public function __construct(private readonly OnboardingService $onboarding)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cliente_id' => ['required', 'string'],
            'nombre' => ['required', 'string'],
            'email' => ['required', 'email'],
            'documento_identidad' => ['required', 'string'],
            'selfie' => ['required', 'string'],
        ]);

        try {
            $resultado = $this->onboarding->onboardear(
                $validated['cliente_id'],
                $validated['nombre'],
                $validated['email'],
                $validated['documento_identidad'],
                $validated['selfie'],
            );
        } catch (OnboardingRejectedException $e) {
            return ApiResponse::error($e->getMessage(), 'onboarding_rechazado', status: 422);
        }

        return ApiResponse::success($resultado, status: 201);
    }
}
