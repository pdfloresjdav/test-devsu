<?php

namespace App\Http\Controllers;

use BP\Common\Auth\JwtClaims;
use BP\Common\Clients\MovementsClient;
use BP\Common\Clients\UpstreamServiceException;
use BP\Common\Http\ApiResponse;
use BP\Common\Http\HandlesUpstreamErrors;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MovementController extends Controller
{
    use HandlesUpstreamErrors;

    public function __construct(private readonly MovementsClient $movements)
    {
    }

    public function index(string $accountId, Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 20);

        try {
            $movements = $this->movements->list($accountId, JwtClaims::bearerToken($request), $limit);
        } catch (UpstreamServiceException $e) {
            return $this->upstreamError($e);
        }

        return ApiResponse::success($movements);
    }
}
