<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LivenessController;
use App\Http\Controllers\MovementController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\TransferController;
use BP\Common\Auth\JwtAuthMiddleware;
use Illuminate\Support\Facades\Route;

// Deliberately without JwtAuthMiddleware: a new customer doesn't have a
// token yet. The real access control for this endpoint is KYC verification.
Route::post('/onboarding', [OnboardingController::class, 'store']);

Route::middleware(JwtAuthMiddleware::class)->group(function () {
    Route::get('/dashboard/{accountId}', [DashboardController::class, 'show']);
    Route::get('/accounts/{accountId}/movements', [MovementController::class, 'index']);
    Route::post('/transfers', [TransferController::class, 'store']);
    Route::post('/revalidate-liveness', [LivenessController::class, 'store']);
});
