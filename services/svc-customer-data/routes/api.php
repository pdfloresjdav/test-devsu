<?php

use App\Http\Controllers\CustomerController;
use BP\Common\Auth\JwtAuthMiddleware;
use Illuminate\Support\Facades\Route;

Route::get('/customers/{customerId}', [CustomerController::class, 'show'])
    ->middleware(JwtAuthMiddleware::class);
