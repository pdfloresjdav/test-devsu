<?php

use App\Http\Controllers\MovimientoController;
use BP\Common\Auth\JwtAuthMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware(JwtAuthMiddleware::class)->group(function () {
    Route::get('/cuentas/{cuentaId}/movimientos', [MovimientoController::class, 'index']);
    Route::post('/cuentas/{cuentaId}/movimientos', [MovimientoController::class, 'store']);
});
