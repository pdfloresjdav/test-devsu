<?php

namespace App\Providers;

use App\Contracts\MovimientosRepository;
use App\Repositories\CachedMovimientosRepository;
use App\Repositories\DynamoDbMovimientosRepository;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MovimientosRepository::class, function ($app) {
            $dynamo = new DynamoDbMovimientosRepository(
                $app->make(DynamoDbClient::class),
                $app->make(Marshaler::class),
                config('services.movimientos.table'),
            );

            return new CachedMovimientosRepository(
                $dynamo,
                Cache::store(config('services.movimientos.cache_store')),
                (int) config('services.movimientos.cache_ttl_seconds'),
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
