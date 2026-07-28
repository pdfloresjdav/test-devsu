<?php

namespace BP\Common\Auth;

interface JwksProviderInterface
{
    /**
     * Devuelve el JWK Set (formato RFC 7517) del emisor dado.
     *
     * @return array<string, mixed>
     */
    public function getJwks(string $issuer): array;
}
