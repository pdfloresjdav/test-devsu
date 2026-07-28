<?php

namespace App\Http\Controllers;

use App\Contracts\UpstreamServiceException;
use BP\Common\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

trait HandlesUpstreamErrors
{
    protected function upstreamError(UpstreamServiceException $e): JsonResponse
    {
        return ApiResponse::error($e->getMessage(), 'upstream_error', status: $e->statusCode);
    }
}
