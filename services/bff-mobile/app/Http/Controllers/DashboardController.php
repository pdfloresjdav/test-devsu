<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use BP\Common\Auth\JwtClaims;
use BP\Common\Clients\UpstreamServiceException;
use BP\Common\Http\ApiResponse;
use BP\Common\Http\HandlesUpstreamErrors;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use HandlesUpstreamErrors;

    public function __construct(private readonly DashboardService $dashboard) {}

    public function show(string $accountId, Request $request): JsonResponse
    {
        try {
            $data = $this->dashboard->get($accountId, JwtClaims::bearerToken($request));
        } catch (UpstreamServiceException $e) {
            return $this->upstreamError($e);
        }

        return ApiResponse::success($data);
    }
}
