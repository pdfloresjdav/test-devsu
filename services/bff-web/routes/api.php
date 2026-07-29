<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MovementController;
use App\Http\Controllers\TransferController;
use BP\Common\Auth\JwtAuthMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware(JwtAuthMiddleware::class)->group(function () {
    Route::get('/dashboard/{accountId}', [DashboardController::class, 'show']);
    Route::get('/accounts/{accountId}/movements', [MovementController::class, 'index']);
    Route::post('/transfers', [TransferController::class, 'store']);
});
