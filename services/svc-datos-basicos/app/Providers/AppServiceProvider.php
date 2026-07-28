<?php

namespace App\Providers;

use App\Clients\FakeClienteComplementarioClient;
use App\Clients\FakeCoreBancarioClient;
use App\Clients\HttpClienteComplementarioClient;
use App\Clients\HttpCoreBancarioClient;
use App\Contracts\ClienteComplementarioClient;
use App\Contracts\CoreBancarioClient;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ClientInterface::class, fn () => new Client());

        $this->app->bind(CoreBancarioClient::class, function ($app) {
            if (config('services.core_bancario.driver') === 'http') {
                return new HttpCoreBancarioClient(
                    $app->make(ClientInterface::class),
                    config('services.core_bancario.base_url'),
                );
            }

            return new FakeCoreBancarioClient();
        });

        $this->app->bind(ClienteComplementarioClient::class, function ($app) {
            if (config('services.cliente_complementario.driver') === 'http') {
                return new HttpClienteComplementarioClient(
                    $app->make(ClientInterface::class),
                    config('services.cliente_complementario.base_url'),
                );
            }

            return new FakeClienteComplementarioClient();
        });
    }

    public function boot(): void
    {
        //
    }
}
