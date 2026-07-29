<?php

namespace App\Http\Controllers;

use App\Contracts\MovementsRepository;
use BP\Common\Auth\JwtClaims;
use BP\Common\Events\EventPublisherInterface;
use BP\Common\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MovementController extends Controller
{
    public function __construct(
        private readonly MovementsRepository $repository,
        private readonly EventPublisherInterface $events,
    ) {
    }

    public function index(string $accountId, Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 20);

        return ApiResponse::success($this->repository->list($accountId, $limit));
    }

    public function store(string $accountId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:debit,credit'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        $movement = $this->repository->register(
            $accountId,
            $validated['type'],
            (float) $validated['amount'],
            $validated['description'],
        );

        $this->events->publish('MovementRegistered', $movement + ['actor' => JwtClaims::actor($request)]);

        return ApiResponse::success($movement, status: 201);
    }
}
