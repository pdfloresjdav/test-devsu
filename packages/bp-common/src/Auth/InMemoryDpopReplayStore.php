<?php

namespace BP\Common\Auth;

/**
 * Store en memoria de proceso. En produccion multi-instancia esto deberia
 * respaldarse en Redis (el mismo Redis usado para Idempotency-Key), para que
 * el replay se detecte entre workers/instancias y no solo dentro de uno.
 */
class InMemoryDpopReplayStore implements DpopReplayStoreInterface
{
    /** @var array<string, int> jti => expiresAt */
    private array $seen = [];

    public function registerOnce(string $jti, int $ttlSeconds): bool
    {
        $this->purgeExpired();

        if (isset($this->seen[$jti])) {
            return false;
        }

        $this->seen[$jti] = time() + $ttlSeconds;

        return true;
    }

    private function purgeExpired(): void
    {
        $now = time();
        foreach ($this->seen as $jti => $expiresAt) {
            if ($expiresAt < $now) {
                unset($this->seen[$jti]);
            }
        }
    }
}
