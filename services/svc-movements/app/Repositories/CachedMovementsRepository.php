<?php

namespace App\Repositories;

use App\Contracts\MovementsRepository;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Cache-Aside pattern (decision 3.8): on read, serves from Redis if
 * available and only hits DynamoDB on a miss; on write, actively
 * invalidates the account's cache (via tags) instead of waiting for the
 * TTL to expire -- avoids serving a stale history right after a new
 * movement is registered.
 */
class CachedMovementsRepository implements MovementsRepository
{
    public function __construct(
        private readonly MovementsRepository $inner,
        private readonly CacheRepository $cache,
        private readonly int $ttlSeconds,
    ) {
    }

    public function list(string $accountId, int $limit = 20): array
    {
        return $this->cache
            ->tags($this->tagFor($accountId))
            ->remember(
                $this->cacheKeyFor($accountId, $limit),
                $this->ttlSeconds,
                fn () => $this->inner->list($accountId, $limit),
            );
    }

    public function register(string $accountId, string $type, float $amount, string $description): array
    {
        $movement = $this->inner->register($accountId, $type, $amount, $description);

        $this->cache->tags($this->tagFor($accountId))->flush();

        return $movement;
    }

    /**
     * @return array<int, string>
     */
    private function tagFor(string $accountId): array
    {
        return ["movements:{$accountId}"];
    }

    private function cacheKeyFor(string $accountId, int $limit): string
    {
        return "movements:{$accountId}:{$limit}";
    }
}
