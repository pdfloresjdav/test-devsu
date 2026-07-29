<?php

namespace BP\Common\Tests\Unit;

use BP\Common\Auth\JwtValidationException;
use BP\Common\Auth\JwtValidator;
use BP\Common\Testing\FakeJwksProvider;
use BP\Common\Testing\RsaKeyPair;
use PHPUnit\Framework\TestCase;

class JwtValidatorTest extends TestCase
{
    private const ISSUER = 'http://localhost:4011';

    private const AUDIENCE = 'bp-web';

    public function test_validates_a_correctly_signed_token(): void
    {
        $keyPair = new RsaKeyPair;
        $validator = new JwtValidator(new FakeJwksProvider($keyPair->toJwks()), self::ISSUER, self::AUDIENCE);

        $token = $keyPair->sign([
            'iss' => self::ISSUER,
            'aud' => self::AUDIENCE,
            'sub' => 'user-1',
            'exp' => time() + 3600,
            'iat' => time(),
        ]);

        $claims = $validator->validate($token);

        $this->assertSame('user-1', $claims['sub']);
    }

    public function test_rejects_a_token_signed_with_another_key(): void
    {
        $keyPair = new RsaKeyPair;
        $otherKey = new RsaKeyPair('other-key');
        $validator = new JwtValidator(new FakeJwksProvider($keyPair->toJwks()), self::ISSUER, self::AUDIENCE);

        $token = $otherKey->sign([
            'iss' => self::ISSUER,
            'aud' => self::AUDIENCE,
            'sub' => 'user-1',
            'exp' => time() + 3600,
            'iat' => time(),
        ]);

        $this->expectException(JwtValidationException::class);
        $validator->validate($token);
    }

    public function test_rejects_an_expired_token(): void
    {
        $keyPair = new RsaKeyPair;
        $validator = new JwtValidator(new FakeJwksProvider($keyPair->toJwks()), self::ISSUER, self::AUDIENCE);

        $token = $keyPair->sign([
            'iss' => self::ISSUER,
            'aud' => self::AUDIENCE,
            'sub' => 'user-1',
            'exp' => time() - 10,
            'iat' => time() - 3600,
        ]);

        $this->expectException(JwtValidationException::class);
        $validator->validate($token);
    }

    public function test_rejects_an_issuer_different_from_the_expected_one(): void
    {
        $keyPair = new RsaKeyPair;
        $validator = new JwtValidator(new FakeJwksProvider($keyPair->toJwks()), self::ISSUER, self::AUDIENCE);

        $token = $keyPair->sign([
            'iss' => 'https://impostor-issuer.test',
            'aud' => self::AUDIENCE,
            'sub' => 'user-1',
            'exp' => time() + 3600,
            'iat' => time(),
        ]);

        $this->expectException(JwtValidationException::class);
        $this->expectExceptionMessage('Unexpected issuer');
        $validator->validate($token);
    }

    public function test_rejects_an_audience_different_from_the_expected_one(): void
    {
        $keyPair = new RsaKeyPair;
        $validator = new JwtValidator(new FakeJwksProvider($keyPair->toJwks()), self::ISSUER, self::AUDIENCE);

        $token = $keyPair->sign([
            'iss' => self::ISSUER,
            'aud' => 'another-client',
            'sub' => 'user-1',
            'exp' => time() + 3600,
            'iat' => time(),
        ]);

        $this->expectException(JwtValidationException::class);
        $this->expectExceptionMessage('Unexpected audience');
        $validator->validate($token);
    }

    public function test_uses_a_different_discovery_issuer_to_fetch_the_jwks_without_affecting_the_iss_validation(): void
    {
        // Real case: inside docker-compose, this process can only reach the
        // issuer via http://mock-oidc:80, but the tokens it issues still
        // carry iss=http://localhost:4011 (what the browser saw when logging in).
        $discoveryIssuer = 'http://mock-oidc:80';
        $keyPair = new RsaKeyPair;
        $jwksProvider = new FakeJwksProvider($keyPair->toJwks());

        $validator = new JwtValidator($jwksProvider, self::ISSUER, self::AUDIENCE, $discoveryIssuer);

        $token = $keyPair->sign([
            'iss' => self::ISSUER,
            'aud' => self::AUDIENCE,
            'sub' => 'user-1',
            'exp' => time() + 3600,
            'iat' => time(),
        ]);

        $claims = $validator->validate($token);

        $this->assertSame('user-1', $claims['sub']);
        $this->assertSame($discoveryIssuer, $jwksProvider->lastRequestedIssuer());
    }
}
