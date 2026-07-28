<?php

namespace BP\Common\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Throwable;

/**
 * Valida un DPoP proof (RFC 9449) para atar el access token a una clave
 * del dispositivo cliente -- decision 3.6 del documento de arquitectura.
 * Mitiga la reutilizacion de un access token robado desde otro origen.
 */
class DpopValidator
{
    public function __construct(
        private readonly DpopReplayStoreInterface $replayStore,
        private readonly int $iatLeewaySeconds = 60,
    ) {
    }

    /**
     * @throws DpopValidationException
     */
    public function validate(
        string $dpopProof,
        string $httpMethod,
        string $httpUri,
        ?string $expectedAccessTokenThumbprint = null,
    ): void {
        [$header, $claims] = $this->decode($dpopProof);

        $this->assertType($header);
        $jwk = $this->extractJwk($header);

        $this->assertHttpMethodMatches($claims, $httpMethod);
        $this->assertHttpUriMatches($claims, $httpUri);
        $this->assertFreshIat($claims);
        $this->assertNotReplayed($claims);

        if ($expectedAccessTokenThumbprint !== null) {
            $this->assertBoundToAccessToken($jwk, $expectedAccessTokenThumbprint);
        }
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function decode(string $dpopProof): array
    {
        $parts = explode('.', $dpopProof);
        if (count($parts) !== 3) {
            throw new DpopValidationException('El DPoP proof no tiene el formato de un JWT.');
        }

        [$headerB64, $payloadB64] = $parts;
        $header = json_decode(JWT::urlsafeB64Decode($headerB64), true);
        $payload = json_decode(JWT::urlsafeB64Decode($payloadB64), true);

        if (! is_array($header) || ! is_array($payload)) {
            throw new DpopValidationException('El DPoP proof no se pudo decodificar.');
        }

        if (! isset($header['jwk']) || ! is_array($header['jwk'])) {
            throw new DpopValidationException('El DPoP proof no trae la clave publica (jwk) en el header.');
        }

        try {
            $key = JWK::parseKey($header['jwk'], $header['alg'] ?? 'ES256');
            JWT::decode($dpopProof, $key);
        } catch (Throwable $e) {
            throw new DpopValidationException("Firma del DPoP proof invalida: {$e->getMessage()}", previous: $e);
        }

        return [$header, $payload];
    }

    /**
     * @param array<string, mixed> $header
     */
    private function assertType(array $header): void
    {
        if (($header['typ'] ?? null) !== 'dpop+jwt') {
            throw new DpopValidationException("El header 'typ' del DPoP proof debe ser 'dpop+jwt'.");
        }
    }

    /**
     * @param array<string, mixed> $header
     *
     * @return array<string, mixed>
     */
    private function extractJwk(array $header): array
    {
        return $header['jwk'];
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function assertHttpMethodMatches(array $claims, string $httpMethod): void
    {
        if (strtoupper((string) ($claims['htm'] ?? '')) !== strtoupper($httpMethod)) {
            throw new DpopValidationException('El claim htm del DPoP proof no coincide con el metodo HTTP de la request.');
        }
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function assertHttpUriMatches(array $claims, string $httpUri): void
    {
        $normalize = static fn (string $uri): string => rtrim(strtok($uri, '?') ?: $uri, '/');

        if ($normalize((string) ($claims['htu'] ?? '')) !== $normalize($httpUri)) {
            throw new DpopValidationException('El claim htu del DPoP proof no coincide con la URL de la request.');
        }
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function assertFreshIat(array $claims): void
    {
        $iat = (int) ($claims['iat'] ?? 0);
        $now = time();

        if ($iat <= 0 || abs($now - $iat) > $this->iatLeewaySeconds) {
            throw new DpopValidationException('El DPoP proof esta expirado o su iat no es valido.');
        }
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function assertNotReplayed(array $claims): void
    {
        $jti = (string) ($claims['jti'] ?? '');

        if ($jti === '') {
            throw new DpopValidationException('El DPoP proof no trae jti.');
        }

        if (! $this->replayStore->registerOnce($jti, $this->iatLeewaySeconds * 2)) {
            throw new DpopValidationException('El DPoP proof ya fue utilizado (posible replay).');
        }
    }

    /**
     * @param array<string, mixed> $jwk
     */
    private function assertBoundToAccessToken(array $jwk, string $expectedThumbprint): void
    {
        if ($this->jwkThumbprint($jwk) !== $expectedThumbprint) {
            throw new DpopValidationException('La clave del DPoP proof no coincide con el cnf.jkt del access token.');
        }
    }

    /**
     * JWK Thumbprint segun RFC 7638 (miembros ordenados alfabeticamente).
     *
     * @param array<string, mixed> $jwk
     */
    public function jwkThumbprint(array $jwk): string
    {
        $members = match ($jwk['kty'] ?? null) {
            'RSA' => ['e' => $jwk['e'], 'kty' => $jwk['kty'], 'n' => $jwk['n']],
            'EC' => ['crv' => $jwk['crv'], 'kty' => $jwk['kty'], 'x' => $jwk['x'], 'y' => $jwk['y']],
            default => throw new DpopValidationException('Tipo de clave (kty) no soportado para thumbprint.'),
        };

        $canonicalJson = json_encode($members, JSON_UNESCAPED_SLASHES);

        return JWT::urlsafeB64Encode(hash('sha256', $canonicalJson, true));
    }
}
