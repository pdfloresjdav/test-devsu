<?php

namespace App\Providers;

use App\Clients\FakeCoreBankingClient;
use App\Clients\FakeCustomerProfileClient;
use App\Clients\HttpCoreBankingClient;
use App\Clients\HttpCustomerProfileClient;
use App\Contracts\CoreBankingClient;
use App\Contracts\CustomerProfileClient;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ClientInterface::class, fn () => new Client);

        $this->app->bind(CoreBankingClient::class, function ($app) {
            if (config('services.core_banking.driver') === 'http') {
                return new HttpCoreBankingClient(
                    $app->make(ClientInterface::class),
                    config('services.core_banking.base_url'),
                );
            }

            return new FakeCoreBankingClient;
        });

        $this->app->bind(CustomerProfileClient::class, function ($app) {
            if (config('services.customer_profile.driver') === 'http') {
                return new HttpCustomerProfileClient(
                    $app->make(ClientInterface::class),
                    config('services.customer_profile.base_url'),
                );
            }

            return new FakeCustomerProfileClient;
        });
    }

    public function boot(): void
    {
        //
    }
}
