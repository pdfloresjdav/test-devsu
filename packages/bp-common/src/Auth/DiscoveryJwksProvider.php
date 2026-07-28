<?php

namespace BP\Common\Auth;

use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Resuelve el JWKS de un emisor OIDC siguiendo el estandar de discovery
 * (RFC 8414 / OpenID Connect Discovery). Funciona igual para el mock-oidc
 * local que para un tenant real de Auth0/Okta -- ambos exponen
 * /.well-known/openid-configuration con un campo jwks_uri.
 */
class DiscoveryJwksProvider implements JwksProviderInterface
{
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly JwksCacheInterface $cache,
        private readonly int $cacheTtlSeconds = 3600,
    ) {
    }

    public function getJwks(string $issuer): array
    {
        $cacheKey = 'bp-common:jwks:' . md5($issuer);

        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $jwksUri = $this->resolveJwksUri($issuer);
        $jwks = $this->fetchJson($jwksUri);

        if (! isset($jwks['keys']) || ! is_array($jwks['keys'])) {
            throw new JwtValidationException("El JWKS de [{$issuer}] no tiene un campo 'keys' valido.");
        }

        $this->cache->put($cacheKey, $jwks, $this->cacheTtlSeconds);

        return $jwks;
    }

    private function resolveJwksUri(string $issuer): string
    {
        $discoveryUrl = rtrim($issuer, '/') . '/.well-known/openid-configuration';
        $discovery = $this->fetchJson($discoveryUrl);

        if (! isset($discovery['jwks_uri']) || ! is_string($discovery['jwks_uri'])) {
            throw new JwtValidationException("El documento de discovery de [{$issuer}] no tiene 'jwks_uri'.");
        }

        return $discovery['jwks_uri'];
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchJson(string $url): array
    {
        try {
            /** @var ResponseInterface $response */
            $response = $this->httpClient->request('GET', $url);
        } catch (Throwable $e) {
            throw new JwtValidationException("No se pudo contactar [{$url}]: {$e->getMessage()}", previous: $e);
        }

        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            throw new JwtValidationException("Respuesta invalida (no JSON) desde [{$url}].");
        }

        return $decoded;
    }
}
