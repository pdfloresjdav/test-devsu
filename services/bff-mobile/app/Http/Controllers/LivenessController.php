<?php

namespace App\Http\Controllers;

use App\Contracts\LivenessProvider;
use BP\Common\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Risk revalidation for sensitive operations (sequence diagram 8.2). Unlike
 * onboarding, there IS an authenticated user here (already operating
 * inside the app), so the route is protected with JwtAuthMiddleware -- it
 * is mediated through the BFF instead of the app calling AWS Rekognition
 * directly, to avoid distributing AWS credentials on the mobile client.
 */
class LivenessController extends Controller
{
    public function __construct(private readonly LivenessProvider $liveness) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reference_selfie' => ['required', 'string'],
            'new_selfie' => ['required', 'string'],
        ]);

        $result = $this->liveness->revalidate($validated['reference_selfie'], $validated['new_selfie']);

        return ApiResponse::success($result);
    }
}
