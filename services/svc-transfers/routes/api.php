<?php

use App\Http\Controllers\TransferController;
use App\Http\Middleware\IdempotencyMiddleware;
use App\Http\Middleware\StepUpAuthMiddleware;
use BP\Common\Auth\JwtAuthMiddleware;
use Illuminate\Support\Facades\Route;

Route::post('/transfers', [TransferController::class, 'store'])
    ->middleware([JwtAuthMiddleware::class, StepUpAuthMiddleware::class, IdempotencyMiddleware::class]);
