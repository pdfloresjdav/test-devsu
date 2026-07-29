<?php

namespace BP\Common\Tests;

use BP\Common\Auth\JwksProviderInterface;
use BP\Common\BpCommonServiceProvider;
use BP\Common\Testing\FakeJwksProvider;
use BP\Common\Testing\RsaKeyPair;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected RsaKeyPair $keyPair;

    protected const ISSUER = 'http://localhost:4011';

    protected const AUDIENCE = 'bp-web';

    protected function getPackageProviders($app): array
    {
        return [BpCommonServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('bp-common.oauth_mode', 'local');
        $app['config']->set('bp-common.jwt.issuer', self::ISSUER);
        $app['config']->set('bp-common.jwt.audience', self::AUDIENCE);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->keyPair = new RsaKeyPair;

        $this->app->singleton(JwksProviderInterface::class, fn () => new FakeJwksProvider($this->keyPair->toJwks()));
    }

    protected function signToken(array $claimsOverride = []): string
    {
        return $this->keyPair->sign(array_merge([
            'iss' => self::ISSUER,
            'aud' => self::AUDIENCE,
            'sub' => 'user-1',
            'exp' => time() + 3600,
            'iat' => time(),
        ], $claimsOverride));
    }
}
