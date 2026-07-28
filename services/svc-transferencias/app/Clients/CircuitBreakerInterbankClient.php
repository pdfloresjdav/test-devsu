<?php

namespace App\Clients;

use App\Contracts\CircuitOpenException;
use App\Contracts\InterbankClient;
use App\Contracts\InterbankException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Throwable;

/**
 * Decorador Circuit Breaker + Retry sobre el cliente interbancario
 * (decision 3.11/9.2): reintenta con backoff exponencial + jitter ante
 * fallas transitorias, y abre el circuito tras varias fallas consecutivas
 * para no seguir golpeando una red interbancaria degradada.
 *
 * El estado del circuito vive en Redis (no en memoria de proceso) para que
 * sea consistente entre workers de Octane e instancias del servicio.
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
    ) {
    }

    public function ejecutar(string $cuentaDestino, float $monto): array
    {
        if ($this->isOpen()) {
            throw new CircuitOpenException('El circuito hacia la red interbancaria esta abierto; se rechaza sin intentar.');
        }

        try {
            $resultado = $this->withRetry(fn () => $this->inner->ejecutar($cuentaDestino, $monto));
            $this->recordSuccess();

            return $resultado;
        } catch (InterbankException $e) {
            $this->recordFailure();
            throw $e;
        }
    }

    /**
     * @template T
     *
     * @param callable(): T $callback
     *
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
