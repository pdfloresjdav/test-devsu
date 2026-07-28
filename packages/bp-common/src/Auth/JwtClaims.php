<?php

namespace BP\Common\Auth;

use Illuminate\Http\Request;

/**
 * Helper para leer los claims que JwtAuthMiddleware deja en el request.
 * Centraliza como se obtiene el "actor" (usuario autenticado) para que
 * todos los servicios que publican eventos de dominio lo hagan de la misma
 * forma -- necesario para que la auditoria pueda atribuir cada accion a un
 * cliente (Fase 5).
 */
class JwtClaims
{
    /**
     * @return array<string, mixed>
     */
    public static function from(Request $request): array
    {
        return $request->attributes->get('jwt_claims', []);
    }

    public static function actor(Request $request, string $default = 'system'): string
    {
        return self::from($request)['sub'] ?? $default;
    }

    /**
     * El token crudo (no decodificado), para reenviarlo tal cual hacia un
     * microservicio interno -- los BFFs no re-emiten credenciales propias,
     * propagan el mismo JWT que ya valido JwtAuthMiddleware (decision 3.5:
     * cada servicio es su propio Resource Server).
     */
    public static function bearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');

        return str_starts_with($header, 'Bearer ') ? substr($header, 7) : null;
    }
}
