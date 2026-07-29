<?php

namespace Tests;

use App\Models\Account;
use BP\Common\Auth\JwksProviderInterface;
use BP\Common\Testing\FakeJwksProvider;
use BP\Common\Testing\RsaKeyPair;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    use DatabaseTransactions;

    protected RsaKeyPair $keyPair;

    protected function setUp(): void
    {
        parent::setUp();

        $this->keyPair = new RsaKeyPair;

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

    protected function createAccount(float $balance = 1000.0): Account
    {
        return Account::create([
            'account_id' => (string) Str::uuid(),
            'balance' => $balance,
        ]);
    }
}
