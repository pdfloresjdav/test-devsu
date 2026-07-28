<?php

use App\Http\Controllers\ClienteController;
use BP\Common\Auth\JwtAuthMiddleware;
use Illuminate\Support\Facades\Route;

Route::get('/clientes/{clienteId}', [ClienteController::class, 'show'])
    ->middleware(JwtAuthMiddleware::class);
