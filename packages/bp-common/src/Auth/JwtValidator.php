<?php

namespace BP\Common\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\SignatureInvalidException;
use stdClass;
use Throwable;
use UnexpectedValueException;

/**
 * Valida un JWT (firma, expiracion, issuer, audience) contra el JWKS del
 * emisor configurado. El mismo flujo sirve tanto para el mock-oidc local
 * como para Auth0/Okta real -- ver decision 3.5 del documento de arquitectura.
 *
 * `$issuer` y `$discoveryIssuer` son conceptos distintos que en Fases 1-10
 * (todo corriendo en el host) coincidian siempre, pero divergen al
 * orquestar los servicios dentro de docker-compose (Fase 11): `$issuer` es
 * el valor exacto que trae el claim `iss` del token (el mismo que ve el
 * navegador, ej. http://localhost:4011, porque ahi es donde el SPA/app
 * movil inicio sesion) -- eso NO cambia. `$discoveryIssuer` es la URL por
 * la que ESTE proceso puede alcanzar fisicamente el emisor para bajar su
 * documento de discovery y su JWKS (ej. http://mock-oidc:80 dentro de la
 * red de docker-compose, un nombre de host que el navegador no resuelve
 * pero un contenedor si). Si no se especifica, se asume igual a `$issuer`
 * (comportamiento anterior, valido cuando todo corre en el mismo host).
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
     * @return array<string, mixed> Claims del token si es valido.
     *
     * @throws JwtValidationException si la firma, expiracion, issuer o audience no son validos.
     */
    public function validate(string $token): array
    {
        $jwks = $this->jwksProvider->getJwks($this->discoveryIssuer);

        try {
            $keys = JWK::parseKeySet($jwks);
            $decoded = JWT::decode($token, $keys);
        } catch (SignatureInvalidException $e) {
            throw new JwtValidationException('Firma del token invalida.', previous: $e);
        } catch (UnexpectedValueException|Throwable $e) {
            throw new JwtValidationException("Token invalido: {$e->getMessage()}", previous: $e);
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
            throw new JwtValidationException("Issuer inesperado: se esperaba [{$expectedIssuer}], vino [{$tokenIssuer}].");
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
            throw new JwtValidationException("Audience inesperada: se esperaba [{$this->audience}].");
        }
    }
}
