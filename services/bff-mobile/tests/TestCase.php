<?php

namespace Tests;

use BP\Common\Auth\JwksProviderInterface;
use BP\Common\Testing\FakeJwksProvider;
use BP\Common\Testing\RsaKeyPair;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Psr\Http\Message\RequestInterface;

abstract class TestCase extends BaseTestCase
{
    protected RsaKeyPair $keyPair;
    protected MockHandler $mockHandler;

    /** @var array<int, RequestInterface> */
    protected array $historyContainer = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->keyPair = new RsaKeyPair();
        $this->app->singleton(JwksProviderInterface::class, fn () => new FakeJwksProvider($this->keyPair->toJwks()));

        $this->mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($this->mockHandler);
        $handlerStack->push(\GuzzleHttp\Middleware::history($this->historyContainer));

        $this->app->singleton(ClientInterface::class, fn () => new Client(['handler' => $handlerStack]));
    }

    protected function signToken(array $claimsOverride = []): string
    {
        return $this->keyPair->sign(array_merge([
            'iss' => config('bp-common.jwt.issuer'),
            'aud' => config('bp-common.jwt.audience'),
            'sub' => 'user-1',
            'exp' => time() + 3600,
            'iat' => time(),
        ], $claimsOverride));
    }
}
