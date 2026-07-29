<?php

namespace App\Providers;

use App\Contracts\MovementsRepository;
use App\Repositories\CachedMovementsRepository;
use App\Repositories\DynamoDbMovementsRepository;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MovementsRepository::class, function ($app) {
            $dynamo = new DynamoDbMovementsRepository(
                $app->make(DynamoDbClient::class),
                $app->make(Marshaler::class),
                config('services.movements.table'),
            );

            return new CachedMovementsRepository(
                $dynamo,
                Cache::store(config('services.movements.cache_store')),
                (int) config('services.movements.cache_ttl_seconds'),
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
