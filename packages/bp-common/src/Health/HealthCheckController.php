<?php

namespace BP\Common\Health;

use BP\Common\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Reusable GET /health: any service that installs bp-common gets it for
 * free (registered by BpCommonServiceProvider), without having to declare
 * its own route or controller.
 */
class HealthCheckController
{
    public function __invoke(): JsonResponse
    {
        return ApiResponse::success([
            'status' => 'ok',
            'service' => config('app.name', 'unknown'),
            'time' => now()->toIso8601String(),
        ]);
    }
}
