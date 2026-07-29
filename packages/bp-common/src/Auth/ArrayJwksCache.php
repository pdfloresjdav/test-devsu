<?php

namespace BP\Common\Auth;

/**
 * In-process memory cache. Good enough for Octane (lives as long as the
 * worker is up) and for tests; in production a service can swap it for a
 * Redis-backed implementation if it needs to share the cache across workers.
 */
class ArrayJwksCache implements JwksCacheInterface
{
    /** @var array<string, array{value: array<string, mixed>, expiresAt: int}> */
    private array $store = [];

    public function get(string $key): ?array
    {
        $entry = $this->store[$key] ?? null;

        if ($entry === null) {
            return null;
        }

        if ($entry['expiresAt'] < time()) {
            unset($this->store[$key]);

            return null;
        }

        return $entry['value'];
    }

    public function put(string $key, array $value, int $ttlSeconds): void
    {
        $this->store[$key] = [
            'value' => $value,
            'expiresAt' => time() + $ttlSeconds,
        ];
    }
}
