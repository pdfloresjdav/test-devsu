<?php

namespace App\Providers;

use App\Clients\CircuitBreakerInterbankClient;
use App\Clients\FakeInterbankClient;
use App\Clients\HttpInterbankClient;
use App\Contracts\InterbankClient;
use App\Http\Middleware\IdempotencyMiddleware;
use App\Http\Middleware\StepUpAuthMiddleware;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ClientInterface::class, fn () => new Client());

        $this->app->singleton(InterbankClient::class, function ($app) {
            $real = config('services.interbank.driver') === 'http'
                ? new HttpInterbankClient($app->make(ClientInterface::class), config('services.interbank.base_url'))
                : new FakeInterbankClient();

            return new CircuitBreakerInterbankClient(
                $real,
                Cache::store('redis'),
                (int) config('services.interbank.circuit_failure_threshold'),
                (int) config('services.interbank.circuit_cooldown_seconds'),
            );
        });

        $this->app->bind(IdempotencyMiddleware::class, fn ($app) => new IdempotencyMiddleware(
            Cache::store('redis'),
            (int) config('services.transfers.idempotency_ttl_seconds'),
        ));

        $this->app->bind(StepUpAuthMiddleware::class, fn () => new StepUpAuthMiddleware(
            (float) config('services.transfers.step_up_threshold'),
        ));
    }

    public function boot(): void
    {
        //
    }
}
