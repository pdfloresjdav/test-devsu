<?php

namespace Tests;

use BP\Common\Auth\JwksProviderInterface;
use BP\Common\Testing\FakeJwksProvider;
use BP\Common\Testing\RsaKeyPair;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected RsaKeyPair $keyPair;

    protected function setUp(): void
    {
        parent::setUp();

        $this->keyPair = new RsaKeyPair();

        $this->app->singleton(JwksProviderInterface::class, fn () => new FakeJwksProvider($this->keyPair->toJwks()));
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
