<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LivenessController;
use App\Http\Controllers\MovimientoController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\TransferController;
use BP\Common\Auth\JwtAuthMiddleware;
use Illuminate\Support\Facades\Route;

// Sin JwtAuthMiddleware a proposito: un cliente nuevo todavia no tiene
// token. El control de acceso real de este endpoint es la verificacion KYC.
Route::post('/onboarding', [OnboardingController::class, 'store']);

Route::middleware(JwtAuthMiddleware::class)->group(function () {
    Route::get('/dashboard/{cuentaId}', [DashboardController::class, 'show']);
    Route::get('/cuentas/{cuentaId}/movimientos', [MovimientoController::class, 'index']);
    Route::post('/transferencias', [TransferController::class, 'store']);
    Route::post('/revalidar-liveness', [LivenessController::class, 'store']);
});
