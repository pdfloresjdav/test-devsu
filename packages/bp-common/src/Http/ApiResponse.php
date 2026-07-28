<?php

namespace BP\Common\Http;

use Illuminate\Http\JsonResponse;

/**
 * Envelope de respuesta comun a todos los servicios, para que un cliente
 * (BFF, SPA, app movil) no tenga que aprender un formato distinto por
 * microservicio.
 */
class ApiResponse
{
    /**
     * @param array<string, mixed> $meta
     */
    public static function success(mixed $data, array $meta = [], int $status = 200): JsonResponse
    {
        return new JsonResponse([
            'data' => $data,
            'meta' => $meta,
        ], $status);
    }

    /**
     * @param array<int, array<string, mixed>> $errors
     */
    public static function error(string $message, string $code = 'error', array $errors = [], int $status = 400): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'code' => $code,
                'message' => $message,
                'errors' => $errors,
            ],
        ], $status);
    }
}
