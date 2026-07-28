<?php

namespace App\Http\Controllers;

use App\Contracts\UpstreamServiceException;
use App\Services\DashboardService;
use BP\Common\Auth\JwtClaims;
use BP\Common\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use HandlesUpstreamErrors;

    public function __construct(private readonly DashboardService $dashboard)
    {
    }

    public function show(string $cuentaId, Request $request): JsonResponse
    {
        try {
            $data = $this->dashboard->obtener($cuentaId, JwtClaims::bearerToken($request));
        } catch (UpstreamServiceException $e) {
            return $this->upstreamError($e);
        }

        return ApiResponse::success($data);
    }
}
