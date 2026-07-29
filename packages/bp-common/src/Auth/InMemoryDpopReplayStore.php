<?php

namespace BP\Common\Auth;

/**
 * In-process memory store. In a multi-instance production setup this
 * should be backed by Redis (the same Redis used for the Idempotency-Key),
 * so replay is detected across workers/instances and not just within one.
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
