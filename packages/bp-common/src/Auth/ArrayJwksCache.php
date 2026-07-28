<?php

namespace BP\Common\Auth;

/**
 * Cache en memoria de proceso. Suficiente para Octane (vive mientras el worker
 * este arriba) y para tests; en produccion un servicio puede sustituirla por
 * una implementacion sobre Redis si necesita compartir el cache entre workers.
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
