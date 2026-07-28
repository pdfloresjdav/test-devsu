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

    public function test_valida_un_token_firmado_correctamente(): void
    {
        $keyPair = new RsaKeyPair();
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

    public function test_rechaza_un_token_firmado_con_otra_llave(): void
    {
        $keyPair = new RsaKeyPair();
        $otraLlave = new RsaKeyPair('otra-key');
        $validator = new JwtValidator(new FakeJwksProvider($keyPair->toJwks()), self::ISSUER, self::AUDIENCE);

        $token = $otraLlave->sign([
            'iss' => self::ISSUER,
            'aud' => self::AUDIENCE,
            'sub' => 'user-1',
            'exp' => time() + 3600,
            'iat' => time(),
        ]);

        $this->expectException(JwtValidationException::class);
        $validator->validate($token);
    }

    public function test_rechaza_un_token_expirado(): void
    {
        $keyPair = new RsaKeyPair();
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

    public function test_rechaza_un_issuer_distinto_al_esperado(): void
    {
        $keyPair = new RsaKeyPair();
        $validator = new JwtValidator(new FakeJwksProvider($keyPair->toJwks()), self::ISSUER, self::AUDIENCE);

        $token = $keyPair->sign([
            'iss' => 'https://issuer-impostor.test',
            'aud' => self::AUDIENCE,
            'sub' => 'user-1',
            'exp' => time() + 3600,
            'iat' => time(),
        ]);

        $this->expectException(JwtValidationException::class);
        $this->expectExceptionMessage('Issuer inesperado');
        $validator->validate($token);
    }

    public function test_rechaza_una_audience_distinta_a_la_esperada(): void
    {
        $keyPair = new RsaKeyPair();
        $validator = new JwtValidator(new FakeJwksProvider($keyPair->toJwks()), self::ISSUER, self::AUDIENCE);

        $token = $keyPair->sign([
            'iss' => self::ISSUER,
            'aud' => 'otro-cliente',
            'sub' => 'user-1',
            'exp' => time() + 3600,
            'iat' => time(),
        ]);

        $this->expectException(JwtValidationException::class);
        $this->expectExceptionMessage('Audience inesperada');
        $validator->validate($token);
    }
}
