<?php

namespace BP\Common\Http;

use BP\Common\Clients\UpstreamServiceException;
use Illuminate\Http\JsonResponse;

/**
 * Trait de conveniencia para controladores de BFF: traduce una falla de un
 * servicio de negocio interno al mismo envelope de error del resto de la
 * API, preservando el status code real.
 */
trait HandlesUpstreamErrors
{
    protected function upstreamError(UpstreamServiceException $e): JsonResponse
    {
        return ApiResponse::error($e->getMessage(), $e->errorCode ?? 'upstream_error', status: $e->statusCode);
    }
}
