<?php

namespace App\Providers;

use App\Contracts\DeliveryTracker;
use App\Repositories\DynamoDbDeliveryTracker;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Aws\Pinpoint\PinpointClient;
use Aws\Ses\SesClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PinpointClient::class, fn () => new PinpointClient([
            'version' => 'latest',
            'region' => config('services.notifications.aws_region'),
            'credentials' => env('AWS_ACCESS_KEY_ID') ? [
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
            ] : null,
        ]));

        $this->app->singleton(SesClient::class, fn () => new SesClient([
            'version' => 'latest',
            'region' => config('services.notifications.aws_region'),
            'endpoint' => config('services.notifications.ses_endpoint') ?: null,
            'credentials' => env('AWS_ACCESS_KEY_ID') ? [
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
            ] : null,
        ]));

        $this->app->singleton(DeliveryTracker::class, fn ($app) => new DynamoDbDeliveryTracker(
            $app->make(DynamoDbClient::class),
            $app->make(Marshaler::class),
            config('services.notifications.table'),
        ));
    }

    public function boot(): void
    {
        //
    }
}
