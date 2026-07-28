<?php

namespace BP\Common\Testing;

use BP\Common\Auth\JwksProviderInterface;

class FakeJwksProvider implements JwksProviderInterface
{
    /**
     * @param array<string, mixed> $jwks
     */
    public function __construct(private readonly array $jwks)
    {
    }

    public function getJwks(string $issuer): array
    {
        return $this->jwks;
    }
}
