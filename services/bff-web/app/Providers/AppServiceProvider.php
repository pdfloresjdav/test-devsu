<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    // The clients toward Customer Data, Movements and Transfers (and the
    // Guzzle ClientInterface they use) are already registered by
    // BpCommonServiceProvider (packages/bp-common) -- see
    // bp-common.internal_services.* in config/bp-common.php and the same
    // *_BASE_URL variables in this service's .env.

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        //
    }
}
