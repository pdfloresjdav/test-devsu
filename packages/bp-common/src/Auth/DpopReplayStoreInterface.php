<?php

namespace BP\Common\Auth;

interface DpopReplayStoreInterface
{
    /**
     * Registers the jti if it hasn't been seen before. Returns false if it
     * already existed (a sign of replay of the same DPoP proof).
     */
    public function registerOnce(string $jti, int $ttlSeconds): bool;
}
