<?php

namespace App\Clients;

use App\Contracts\CircuitOpenException;
use App\Contracts\InterbankClient;
use App\Contracts\InterbankException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Throwable;

/**
 * Circuit Breaker + Retry decorator over the interbank client (decision
 * 3.11/9.2): retries with exponential backoff + jitter on transient
 * failures, and opens the circuit after several consecutive failures to
 * stop hammering a degraded interbank network.
 *
 * The circuit state lives in Redis (not process memory) so it's
 * consistent across Octane workers and service instances.
 */
class CircuitBreakerInterbankClient implements InterbankClient
{
    private const CACHE_KEY_FAILURES = 'circuit-breaker:interbank:failures';

    private const CACHE_KEY_OPENED_AT = 'circuit-breaker:interbank:opened-at';

    public function __construct(
        private readonly InterbankClient $inner,
        private readonly CacheRepository $cache,
        private readonly int $failureThreshold,
        private readonly int $cooldownSeconds,
        private readonly int $maxAttempts = 3,
    ) {}

    public function execute(string $destinationAccount, float $amount): array
    {
        if ($this->isOpen()) {
            throw new CircuitOpenException('The circuit toward the interbank network is open; rejecting without attempting.');
        }

        try {
            $result = $this->withRetry(fn () => $this->inner->execute($destinationAccount, $amount));
            $this->recordSuccess();

            return $result;
        } catch (InterbankException $e) {
            $this->recordFailure();
            throw $e;
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function withRetry(callable $callback): mixed
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return $callback();
            } catch (Throwable $e) {
                if ($attempt >= $this->maxAttempts) {
                    throw $e;
                }

                $backoffMs = (2 ** $attempt) * 50;
                $jitterMs = random_int(0, 50);
                usleep(($backoffMs + $jitterMs) * 1000);
            }
        }
    }

    private function isOpen(): bool
    {
        $openedAt = $this->cache->get(self::CACHE_KEY_OPENED_AT);

        if ($openedAt === null) {
            return false;
        }

        if (time() - $openedAt >= $this->cooldownSeconds) {
            $this->reset();

            return false;
        }

        return true;
    }

    private function recordSuccess(): void
    {
        $this->reset();
    }

    private function recordFailure(): void
    {
        $failures = (int) $this->cache->get(self::CACHE_KEY_FAILURES, 0) + 1;
        $this->cache->put(self::CACHE_KEY_FAILURES, $failures, $this->cooldownSeconds * 10);

        if ($failures >= $this->failureThreshold) {
            $this->cache->put(self::CACHE_KEY_OPENED_AT, time(), $this->cooldownSeconds * 10);
        }
    }

    private function reset(): void
    {
        $this->cache->forget(self::CACHE_KEY_FAILURES);
        $this->cache->forget(self::CACHE_KEY_OPENED_AT);
    }
}
