<?php

namespace BP\Common;

use BP\Common\Auth\ArrayJwksCache;
use BP\Common\Auth\DiscoveryJwksProvider;
use BP\Common\Auth\DpopReplayStoreInterface;
use BP\Common\Auth\DpopValidator;
use BP\Common\Auth\InMemoryDpopReplayStore;
use BP\Common\Auth\JwksCacheInterface;
use BP\Common\Auth\JwksProviderInterface;
use BP\Common\Auth\JwtAuthMiddleware;
use BP\Common\Auth\JwtValidator;
use BP\Common\Clients\CustomerDataClient;
use BP\Common\Clients\HttpCustomerDataClient;
use BP\Common\Clients\HttpMovementsClient;
use BP\Common\Clients\HttpTransfersClient;
use BP\Common\Clients\MovementsClient;
use BP\Common\Clients\TransfersClient;
use BP\Common\Events\EventBridgeEventPublisher;
use BP\Common\Events\EventPublisherInterface;
use BP\Common\Health\HealthCheckController;
use BP\Common\Http\CorrelationIdMiddleware;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Aws\EventBridge\EventBridgeClient;
use Aws\Sqs\SqsClient;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class BpCommonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/bp-common.php', 'bp-common');

        $this->app->singleton(ClientInterface::class, fn () => new Client());
        $this->app->singleton(JwksCacheInterface::class, fn () => new ArrayJwksCache());
        $this->app->singleton(DpopReplayStoreInterface::class, fn () => new InMemoryDpopReplayStore());

        $this->app->singleton(JwksProviderInterface::class, fn ($app) => new DiscoveryJwksProvider(
            httpClient: $app->make(ClientInterface::class),
            cache: $app->make(JwksCacheInterface::class),
            cacheTtlSeconds: (int) config('bp-common.jwt.jwks_cache_ttl', 3600),
        ));

        $this->app->singleton(JwtValidator::class, fn ($app) => new JwtValidator(
            jwksProvider: $app->make(JwksProviderInterface::class),
            issuer: $this->resolveIssuer(),
            audience: $this->resolveAudience(),
            discoveryIssuer: config('bp-common.jwt.discovery_issuer') ?: null,
        ));

        $this->app->singleton(DpopValidator::class, fn ($app) => new DpopValidator(
            replayStore: $app->make(DpopReplayStoreInterface::class),
            iatLeewaySeconds: (int) config('bp-common.dpop.iat_leeway_seconds', 60),
        ));

        $this->app->bind(JwtAuthMiddleware::class, fn ($app) => new JwtAuthMiddleware(
            jwtValidator: $app->make(JwtValidator::class),
            dpopValidator: $app->make(DpopValidator::class),
            dpopEnforced: (bool) config('bp-common.dpop.enforced', false),
        ));

        $this->app->singleton(EventBridgeClient::class, fn () => new EventBridgeClient([
            'version' => 'latest',
            'region' => config('bp-common.events.region'),
            'endpoint' => config('bp-common.events.endpoint') ?: null,
            'credentials' => $this->resolveAwsCredentials(),
        ]));

        $this->app->singleton(EventPublisherInterface::class, fn ($app) => new EventBridgeEventPublisher(
            client: $app->make(EventBridgeClient::class),
            eventBusName: config('bp-common.events.event_bus_name'),
            source: config('bp-common.events.source'),
        ));

        $this->app->singleton(DynamoDbClient::class, fn () => new DynamoDbClient([
            'version' => 'latest',
            'region' => config('bp-common.dynamodb.region'),
            'endpoint' => config('bp-common.dynamodb.endpoint') ?: null,
            'credentials' => $this->resolveAwsCredentials(),
        ]));

        $this->app->singleton(Marshaler::class, fn () => new Marshaler());

        $this->app->singleton(SqsClient::class, fn () => new SqsClient([
            'version' => 'latest',
            'region' => config('bp-common.sqs.region'),
            'endpoint' => config('bp-common.sqs.endpoint') ?: null,
            'credentials' => $this->resolveAwsCredentials(),
        ]));

        $this->app->singleton(CustomerDataClient::class, fn ($app) => new HttpCustomerDataClient(
            $app->make(ClientInterface::class),
            config('bp-common.internal_services.customer_data_url'),
        ));

        $this->app->singleton(MovementsClient::class, fn ($app) => new HttpMovementsClient(
            $app->make(ClientInterface::class),
            config('bp-common.internal_services.movements_url'),
        ));

        $this->app->singleton(TransfersClient::class, fn ($app) => new HttpTransfersClient(
            $app->make(ClientInterface::class),
            config('bp-common.internal_services.transfers_url'),
        ));
    }

    /**
     * @return array{key: string, secret: string}|null
     */
    private function resolveAwsCredentials(): ?array
    {
        // LocalStack accepts any credential; on real AWS the service's IAM
        // Task Role should be used instead (decision 3.14) and these env
        // vars should not be defined.
        if (! env('AWS_ACCESS_KEY_ID')) {
            return null;
        }

        return [
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
        ];
    }

    public function boot(Router $router): void
    {
        $router->aliasMiddleware('bp.jwt', JwtAuthMiddleware::class);
        $router->aliasMiddleware('bp.correlation', CorrelationIdMiddleware::class);

        $this->app->make(Kernel::class)->pushMiddleware(CorrelationIdMiddleware::class);

        $router->get('/health', HealthCheckController::class)->name('bp-common.health');

        $this->publishes([
            __DIR__ . '/../config/bp-common.php' => config_path('bp-common.php'),
        ], 'bp-common-config');
    }

    private function resolveIssuer(): string
    {
        return config('bp-common.oauth_mode') === 'auth0'
            ? 'https://' . config('bp-common.auth0.domain')
            : config('bp-common.jwt.issuer');
    }

    private function resolveAudience(): string
    {
        return config('bp-common.oauth_mode') === 'auth0'
            ? config('bp-common.auth0.audience')
            : config('bp-common.jwt.audience');
    }
}
