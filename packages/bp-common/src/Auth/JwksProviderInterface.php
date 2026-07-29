<?php

namespace BP\Common\Auth;

interface JwksProviderInterface
{
    /**
     * Returns the JWK Set (RFC 7517 format) of the given issuer.
     *
     * @return array<string, mixed>
     */
    public function getJwks(string $issuer): array;
}
