<?php

namespace App\Http\Controllers;

use BP\Common\Auth\JwtClaims;
use BP\Common\Clients\TransfersClient;
use BP\Common\Clients\UpstreamServiceException;
use BP\Common\Http\ApiResponse;
use BP\Common\Http\HandlesUpstreamErrors;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransferController extends Controller
{
    use HandlesUpstreamErrors;

    public function __construct(private readonly TransfersClient $transfers) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_account' => ['required', 'string'],
            'destination_account' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $idempotencyKey = $request->header('Idempotency-Key');

        if (! $idempotencyKey) {
            return ApiResponse::error('Missing Idempotency-Key header.', 'missing_idempotency_key', status: 400);
        }

        try {
            $result = $this->transfers->create($validated, $idempotencyKey, JwtClaims::bearerToken($request));
        } catch (UpstreamServiceException $e) {
            return $this->upstreamError($e);
        }

        return ApiResponse::success($result, status: 201);
    }
}
