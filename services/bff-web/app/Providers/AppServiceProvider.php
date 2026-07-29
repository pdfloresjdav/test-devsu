<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    // Los clientes hacia Datos Basicos, Movimientos y Transferencias (y el
    // ClientInterface de Guzzle que usan) ya vienen registrados por
    // BpCommonServiceProvider (packages/bp-common) -- ver
    // bp-common.internal_services.* en config/bp-common.php y las mismas
    // variables *_BASE_URL en el .env de este servicio.

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        //
    }
}
