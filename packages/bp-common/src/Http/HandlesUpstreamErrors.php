<?php

namespace BP\Common\Http;

use BP\Common\Clients\UpstreamServiceException;
use Illuminate\Http\JsonResponse;

/**
 * Convenience trait for BFF controllers: translates a failure from an
 * internal business service into the same error envelope as the rest of
 * the API, preserving the real status code.
 */
trait HandlesUpstreamErrors
{
    protected function upstreamError(UpstreamServiceException $e): JsonResponse
    {
        return ApiResponse::error($e->getMessage(), $e->errorCode ?? 'upstream_error', status: $e->statusCode);
    }
}
