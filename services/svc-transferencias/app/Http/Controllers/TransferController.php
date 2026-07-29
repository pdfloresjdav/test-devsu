<?php

namespace App\Http\Controllers;

use App\Contracts\InsufficientBalanceException;
use App\Services\TransferOrchestrator;
use BP\Common\Auth\JwtClaims;
use BP\Common\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransferController extends Controller
{
    public function __construct(private readonly TransferOrchestrator $orchestrator)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_account' => ['required', 'string'],
            'destination_account' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $idempotencyKey = $request->attributes->get('idempotency_key');

        try {
            $transfer = $this->orchestrator->execute(
                $validated['source_account'],
                $validated['destination_account'],
                (float) $validated['amount'],
                $validated['description'] ?? '',
                $idempotencyKey,
                JwtClaims::actor($request),
            );
        } catch (InsufficientBalanceException $e) {
            return ApiResponse::error($e->getMessage(), 'insufficient_balance', status: 422);
        }

        return ApiResponse::success([
            'transfer_id' => $transfer->transfer_id,
            'source_account' => $transfer->source_account,
            'destination_account' => $transfer->destination_account,
            'amount' => (float) $transfer->amount,
            'description' => $transfer->description,
            'status' => $transfer->status,
            'failure_reason' => $transfer->failure_reason,
        ], status: 201);
    }
}
