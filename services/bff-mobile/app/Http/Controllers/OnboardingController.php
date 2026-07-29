<?php

namespace App\Http\Controllers;

use App\Contracts\OnboardingRejectedException;
use App\Services\OnboardingService;
use BP\Common\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Deliberately NOT protected with JwtAuthMiddleware: a new customer doesn't
 * have a token yet. KYC verification is the real access control for this
 * endpoint. A production system would also add app attestation / rate
 * limiting -- out of scope for this phase.
 */
class OnboardingController extends Controller
{
    public function __construct(private readonly OnboardingService $onboarding) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'string'],
            'name' => ['required', 'string'],
            'email' => ['required', 'email'],
            'identity_document' => ['required', 'string'],
            'selfie' => ['required', 'string'],
        ]);

        try {
            $result = $this->onboarding->onboard(
                $validated['customer_id'],
                $validated['name'],
                $validated['email'],
                $validated['identity_document'],
                $validated['selfie'],
            );
        } catch (OnboardingRejectedException $e) {
            return ApiResponse::error($e->getMessage(), 'onboarding_rejected', status: 422);
        }

        return ApiResponse::success($result, status: 201);
    }
}
