<?php

namespace App\Providers;

use App\Clients\HttpDatosBasicosClient;
use App\Clients\HttpMovimientosClient;
use App\Clients\HttpTransferenciasClient;
use App\Contracts\DatosBasicosClient;
use App\Contracts\MovimientosClient;
use App\Contracts\TransferenciasClient;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ClientInterface::class, fn () => new Client());

        $this->app->singleton(DatosBasicosClient::class, fn ($app) => new HttpDatosBasicosClient(
            $app->make(ClientInterface::class),
            config('services.datos_basicos.base_url'),
        ));

        $this->app->singleton(MovimientosClient::class, fn ($app) => new HttpMovimientosClient(
            $app->make(ClientInterface::class),
            config('services.movimientos.base_url'),
        ));

        $this->app->singleton(TransferenciasClient::class, fn ($app) => new HttpTransferenciasClient(
            $app->make(ClientInterface::class),
            config('services.transferencias.base_url'),
        ));
    }

    public function boot(): void
    {
        //
    }
}
