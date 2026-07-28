<?php

namespace BP\Common\Auth;

interface DpopReplayStoreInterface
{
    /**
     * Registra el jti si no se ha visto antes. Devuelve false si ya existia
     * (indicio de replay del mismo DPoP proof).
     */
    public function registerOnce(string $jti, int $ttlSeconds): bool;
}
