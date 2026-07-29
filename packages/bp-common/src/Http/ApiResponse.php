<?php

namespace BP\Common\Http;

use Illuminate\Http\JsonResponse;

/**
 * Response envelope shared by every service, so a client (BFF, SPA,
 * mobile app) doesn't have to learn a different format per microservice.
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
