<?php

use App\Http\Controllers\MovementController;
use BP\Common\Auth\JwtAuthMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware(JwtAuthMiddleware::class)->group(function () {
    Route::get('/accounts/{accountId}/movements', [MovementController::class, 'index']);
    Route::post('/accounts/{accountId}/movements', [MovementController::class, 'store']);
});
