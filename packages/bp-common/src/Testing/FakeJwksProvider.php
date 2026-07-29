<?php

namespace BP\Common\Testing;

use BP\Common\Auth\JwksProviderInterface;

class FakeJwksProvider implements JwksProviderInterface
{
    private ?string $lastRequestedIssuer = null;

    /**
     * @param array<string, mixed> $jwks
     */
    public function __construct(private readonly array $jwks)
    {
    }

    public function getJwks(string $issuer): array
    {
        $this->lastRequestedIssuer = $issuer;

        return $this->jwks;
    }

    public function lastRequestedIssuer(): ?string
    {
        return $this->lastRequestedIssuer;
    }
}
