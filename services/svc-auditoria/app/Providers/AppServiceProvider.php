<?php

namespace App\Providers;

use App\Contracts\AuditRepository;
use App\Repositories\DynamoDbAuditRepository;
use App\Services\WormArchiver;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Aws\S3\S3Client;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(S3Client::class, fn () => new S3Client([
            'version' => 'latest',
            'region' => config('services.audit.s3_region'),
            'endpoint' => config('services.audit.s3_endpoint') ?: null,
            'use_path_style_endpoint' => (bool) config('services.audit.s3_endpoint'),
            'credentials' => env('AWS_ACCESS_KEY_ID') ? [
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
            ] : null,
        ]));

        $this->app->singleton(AuditRepository::class, fn ($app) => new DynamoDbAuditRepository(
            $app->make(DynamoDbClient::class),
            $app->make(Marshaler::class),
            config('services.audit.table'),
        ));

        $this->app->singleton(WormArchiver::class, fn ($app) => new WormArchiver(
            $app->make(S3Client::class),
            config('services.audit.bucket'),
        ));
    }

    public function boot(): void
    {
        //
    }
}
