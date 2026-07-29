<?php

namespace BP\Common\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Throwable;

/**
 * Validates a DPoP proof (RFC 9449) to bind the access token to a client
 * device key -- decision 3.6 of the architecture document. Mitigates
 * reuse of a stolen access token from a different origin.
 */
class DpopValidator
{
    public function __construct(
        private readonly DpopReplayStoreInterface $replayStore,
        private readonly int $iatLeewaySeconds = 60,
    ) {}

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
            throw new DpopValidationException('The DPoP proof is not shaped like a JWT.');
        }

        [$headerB64, $payloadB64] = $parts;
        $header = json_decode(JWT::urlsafeB64Decode($headerB64), true);
        $payload = json_decode(JWT::urlsafeB64Decode($payloadB64), true);

        if (! is_array($header) || ! is_array($payload)) {
            throw new DpopValidationException('The DPoP proof could not be decoded.');
        }

        if (! isset($header['jwk']) || ! is_array($header['jwk'])) {
            throw new DpopValidationException("The DPoP proof doesn't carry the public key (jwk) in its header.");
        }

        try {
            $key = JWK::parseKey($header['jwk'], $header['alg'] ?? 'ES256');
            JWT::decode($dpopProof, $key);
        } catch (Throwable $e) {
            throw new DpopValidationException("Invalid DPoP proof signature: {$e->getMessage()}", previous: $e);
        }

        return [$header, $payload];
    }

    /**
     * @param  array<string, mixed>  $header
     */
    private function assertType(array $header): void
    {
        if (($header['typ'] ?? null) !== 'dpop+jwt') {
            throw new DpopValidationException("The DPoP proof's 'typ' header must be 'dpop+jwt'.");
        }
    }

    /**
     * @param  array<string, mixed>  $header
     * @return array<string, mixed>
     */
    private function extractJwk(array $header): array
    {
        return $header['jwk'];
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function assertHttpMethodMatches(array $claims, string $httpMethod): void
    {
        if (strtoupper((string) ($claims['htm'] ?? '')) !== strtoupper($httpMethod)) {
            throw new DpopValidationException("The DPoP proof's htm claim doesn't match the request's HTTP method.");
        }
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function assertHttpUriMatches(array $claims, string $httpUri): void
    {
        $normalize = static fn (string $uri): string => rtrim(strtok($uri, '?') ?: $uri, '/');

        if ($normalize((string) ($claims['htu'] ?? '')) !== $normalize($httpUri)) {
            throw new DpopValidationException("The DPoP proof's htu claim doesn't match the request's URL.");
        }
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function assertFreshIat(array $claims): void
    {
        $iat = (int) ($claims['iat'] ?? 0);
        $now = time();

        if ($iat <= 0 || abs($now - $iat) > $this->iatLeewaySeconds) {
            throw new DpopValidationException('The DPoP proof is expired or its iat is not valid.');
        }
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function assertNotReplayed(array $claims): void
    {
        $jti = (string) ($claims['jti'] ?? '');

        if ($jti === '') {
            throw new DpopValidationException("The DPoP proof doesn't carry a jti.");
        }

        if (! $this->replayStore->registerOnce($jti, $this->iatLeewaySeconds * 2)) {
            throw new DpopValidationException('This DPoP proof was already used (possible replay).');
        }
    }

    /**
     * @param  array<string, mixed>  $jwk
     */
    private function assertBoundToAccessToken(array $jwk, string $expectedThumbprint): void
    {
        if ($this->jwkThumbprint($jwk) !== $expectedThumbprint) {
            throw new DpopValidationException("The DPoP proof's key doesn't match the access token's cnf.jkt.");
        }
    }

    /**
     * JWK Thumbprint per RFC 7638 (alphabetically ordered members).
     *
     * @param  array<string, mixed>  $jwk
     */
    public function jwkThumbprint(array $jwk): string
    {
        $members = match ($jwk['kty'] ?? null) {
            'RSA' => ['e' => $jwk['e'], 'kty' => $jwk['kty'], 'n' => $jwk['n']],
            'EC' => ['crv' => $jwk['crv'], 'kty' => $jwk['kty'], 'x' => $jwk['x'], 'y' => $jwk['y']],
            default => throw new DpopValidationException('Unsupported key type (kty) for thumbprint.'),
        };

        $canonicalJson = json_encode($members, JSON_UNESCAPED_SLASHES);

        return JWT::urlsafeB64Encode(hash('sha256', $canonicalJson, true));
    }
}
