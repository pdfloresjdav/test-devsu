<?php

namespace BP\Common\Auth;

interface JwksCacheInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function get(string $key): ?array;

    /**
     * @param  array<string, mixed>  $value
     */
    public function put(string $key, array $value, int $ttlSeconds): void;
}
