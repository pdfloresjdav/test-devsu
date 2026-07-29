<?php

namespace App\Providers;

use App\Clients\Auth0IdentityProviderClient;
use App\Clients\FakeIdentityProviderClient;
use App\Clients\FakeKycProvider;
use App\Clients\FakeLivenessProvider;
use App\Clients\OnfidoKycProvider;
use App\Clients\RekognitionLivenessProvider;
use App\Contracts\IdentityProviderClient;
use App\Contracts\KycProvider;
use App\Contracts\LivenessProvider;
use Aws\Rekognition\RekognitionClient;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    // The clients toward Customer Data, Movements and Transfers are
    // already registered by BpCommonServiceProvider (packages/bp-common).

    public function register(): void
    {
        $this->app->singleton(ClientInterface::class, fn () => new Client);

        $this->app->singleton(KycProvider::class, function ($app) {
            if (config('services.onboarding.kyc_driver') === 'http') {
                return new OnfidoKycProvider(
                    $app->make(ClientInterface::class),
                    config('services.onboarding.kyc_base_url'),
                    config('services.onboarding.kyc_api_key'),
                );
            }

            return new FakeKycProvider;
        });

        $this->app->singleton(IdentityProviderClient::class, function ($app) {
            if (config('services.onboarding.identity_driver') === 'auth0') {
                return new Auth0IdentityProviderClient(
                    $app->make(ClientInterface::class),
                    config('services.onboarding.auth0_management_url'),
                    config('services.onboarding.auth0_management_token'),
                );
            }

            return new FakeIdentityProviderClient;
        });

        $this->app->singleton(RekognitionClient::class, fn () => new RekognitionClient([
            'version' => 'latest',
            'region' => config('services.onboarding.aws_region'),
            'credentials' => env('AWS_ACCESS_KEY_ID') ? [
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
            ] : null,
        ]));

        $this->app->singleton(LivenessProvider::class, fn ($app) => config('services.onboarding.liveness_driver') === 'aws'
            ? new RekognitionLivenessProvider($app->make(RekognitionClient::class))
            : new FakeLivenessProvider);
    }

    public function boot(): void
    {
        //
    }
}
