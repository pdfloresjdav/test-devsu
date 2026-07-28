<?php

namespace BP\Common\Tests\Support;

use Firebase\JWT\JWT;

/**
 * Genera un par de llaves RSA en memoria y su representacion JWK, para
 * poder firmar y verificar JWTs de prueba sin depender de una red ni de
 * un servidor OIDC real.
 */
class RsaKeyPair
{
    public readonly string $privateKeyPem;
    public readonly string $publicKeyPem;
    public readonly string $kid;

    public function __construct(string $kid = 'test-key-1')
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        openssl_pkey_export($resource, $privateKeyPem);
        $details = openssl_pkey_get_details($resource);

        $this->privateKeyPem = $privateKeyPem;
        $this->publicKeyPem = $details['key'];
        $this->kid = $kid;
    }

    /**
     * @return array<string, mixed>
     */
    public function toJwks(): array
    {
        $resource = openssl_pkey_get_public($this->publicKeyPem);
        $details = openssl_pkey_get_details($resource);
        $rsa = $details['rsa'];

        return [
            'keys' => [
                [
                    'kty' => 'RSA',
                    'kid' => $this->kid,
                    'use' => 'sig',
                    'alg' => 'RS256',
                    'n' => JWT::urlsafeB64Encode($rsa['n']),
                    'e' => JWT::urlsafeB64Encode($rsa['e']),
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $claims
     */
    public function sign(array $claims): string
    {
        return JWT::encode($claims, $this->privateKeyPem, 'RS256', $this->kid);
    }
}
