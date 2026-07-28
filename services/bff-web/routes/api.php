<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MovimientoController;
use App\Http\Controllers\TransferController;
use BP\Common\Auth\JwtAuthMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware(JwtAuthMiddleware::class)->group(function () {
    Route::get('/dashboard/{cuentaId}', [DashboardController::class, 'show']);
    Route::get('/cuentas/{cuentaId}/movimientos', [MovimientoController::class, 'index']);
    Route::post('/transferencias', [TransferController::class, 'store']);
});
