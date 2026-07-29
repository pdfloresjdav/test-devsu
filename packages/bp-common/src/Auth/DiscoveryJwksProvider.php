<?php

namespace BP\Common\Auth;

use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Resolves the JWKS of an OIDC issuer following the discovery standard
 * (RFC 8414 / OpenID Connect Discovery). Works the same way for the local
 * mock-oidc as for a real Auth0/Okta tenant -- both expose
 * /.well-known/openid-configuration with a jwks_uri field.
 */
class DiscoveryJwksProvider implements JwksProviderInterface
{
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly JwksCacheInterface $cache,
        private readonly int $cacheTtlSeconds = 3600,
    ) {}

    public function getJwks(string $issuer): array
    {
        $cacheKey = 'bp-common:jwks:'.md5($issuer);

        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $jwksUri = $this->resolveJwksUri($issuer);
        $jwks = $this->fetchJson($jwksUri);

        if (! isset($jwks['keys']) || ! is_array($jwks['keys'])) {
            throw new JwtValidationException("The JWKS from [{$issuer}] doesn't have a valid 'keys' field.");
        }

        $this->cache->put($cacheKey, $jwks, $this->cacheTtlSeconds);

        return $jwks;
    }

    private function resolveJwksUri(string $issuer): string
    {
        $discoveryUrl = rtrim($issuer, '/').'/.well-known/openid-configuration';
        $discovery = $this->fetchJson($discoveryUrl);

        if (! isset($discovery['jwks_uri']) || ! is_string($discovery['jwks_uri'])) {
            throw new JwtValidationException("The discovery document from [{$issuer}] doesn't have a 'jwks_uri'.");
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
            throw new JwtValidationException("Could not reach [{$url}]: {$e->getMessage()}", previous: $e);
        }

        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            throw new JwtValidationException("Invalid response (not JSON) from [{$url}].");
        }

        return $decoded;
    }
}
