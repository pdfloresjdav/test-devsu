<?php

namespace BP\Common\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\SignatureInvalidException;
use stdClass;
use Throwable;
use UnexpectedValueException;

/**
 * Validates a JWT (signature, expiration, issuer, audience) against the
 * configured issuer's JWKS. The same flow serves both the local mock-oidc
 * and a real Auth0/Okta tenant -- see decision 3.5 of the architecture
 * document.
 *
 * `$issuer` and `$discoveryIssuer` are distinct concepts that in Phases
 * 1-10 (everything running on the host) always matched, but diverge once
 * the services are orchestrated inside docker-compose (Phase 11): `$issuer`
 * is the exact value carried by the token's `iss` claim (the same one the
 * browser sees, e.g. http://localhost:4011, because that's where the
 * SPA/mobile app logged in) -- that does NOT change. `$discoveryIssuer` is
 * the URL by which THIS process can physically reach the issuer to fetch
 * its discovery document and JWKS (e.g. http://mock-oidc:80 inside the
 * docker-compose network, a hostname the browser can't resolve but a
 * container can). If not specified, it's assumed to be the same as
 * `$issuer` (previous behavior, valid when everything runs on the same host).
 */
class JwtValidator
{
    private readonly string $discoveryIssuer;

    public function __construct(
        private readonly JwksProviderInterface $jwksProvider,
        private readonly string $issuer,
        private readonly string $audience,
        ?string $discoveryIssuer = null,
    ) {
        $this->discoveryIssuer = $discoveryIssuer ?: $issuer;
    }

    /**
     * @return array<string, mixed> The token's claims if valid.
     *
     * @throws JwtValidationException if the signature, expiration, issuer, or audience are invalid.
     */
    public function validate(string $token): array
    {
        $jwks = $this->jwksProvider->getJwks($this->discoveryIssuer);

        try {
            $keys = JWK::parseKeySet($jwks);
            $decoded = JWT::decode($token, $keys);
        } catch (SignatureInvalidException $e) {
            throw new JwtValidationException('Invalid token signature.', previous: $e);
        } catch (UnexpectedValueException|Throwable $e) {
            throw new JwtValidationException("Invalid token: {$e->getMessage()}", previous: $e);
        }

        $claims = $this->toArray($decoded);

        $this->assertIssuerMatches($claims);
        $this->assertAudienceMatches($claims);

        return $claims;
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(stdClass $decoded): array
    {
        return json_decode(json_encode($decoded), true);
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function assertIssuerMatches(array $claims): void
    {
        $tokenIssuer = rtrim((string) ($claims['iss'] ?? ''), '/');
        $expectedIssuer = rtrim($this->issuer, '/');

        if ($tokenIssuer !== $expectedIssuer) {
            throw new JwtValidationException("Unexpected issuer: expected [{$expectedIssuer}], got [{$tokenIssuer}].");
        }
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function assertAudienceMatches(array $claims): void
    {
        $audienceClaim = $claims['aud'] ?? null;
        $audiences = is_array($audienceClaim) ? $audienceClaim : [$audienceClaim];

        if (! in_array($this->audience, $audiences, true)) {
            throw new JwtValidationException("Unexpected audience: expected [{$this->audience}].");
        }
    }
}
