<?php

namespace Tests\Feature;

use App\Clients\CircuitBreakerInterbankClient;
use App\Contracts\CircuitOpenException;
use App\Contracts\InterbankClient;
use App\Contracts\InterbankException;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CircuitBreakerInterbankClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->clearCircuitState();
    }

    protected function tearDown(): void
    {
        // The circuit state lives in real Redis (on purpose, to be
        // consistent across workers/instances) and DatabaseTransactions
        // doesn't revert it -- if not cleaned up here, a circuit opened by
        // this test contaminates any other test (in this class or others)
        // that uses the same InterbankClient from the container.
        $this->clearCircuitState();

        parent::tearDown();
    }

    private function clearCircuitState(): void
    {
        Cache::store('redis')->forget('circuit-breaker:interbank:failures');
        Cache::store('redis')->forget('circuit-breaker:interbank:opened-at');
    }

    private function alwaysFailingInterbankClient(array &$calls): InterbankClient
    {
        return new class($calls) implements InterbankClient {
            public function __construct(private array &$calls)
            {
            }

            public function execute(string $destinationAccount, float $amount): array
            {
                $this->calls[] = $destinationAccount;
                throw new InterbankException('the destination bank is not responding');
            }
        };
    }

    public function test_opens_the_circuit_after_the_failure_threshold_and_stops_calling_the_inner_client(): void
    {
        $calls = [];
        $breaker = new CircuitBreakerInterbankClient(
            $this->alwaysFailingInterbankClient($calls),
            Cache::store('redis'),
            failureThreshold: 2,
            cooldownSeconds: 30,
            maxAttempts: 1, // no internal retries, to count failures predictably
        );

        // First 2 calls: fail and open the circuit (threshold = 2).
        try {
            $breaker->execute('X', 10);
        } catch (InterbankException) {
        }
        try {
            $breaker->execute('X', 10);
        } catch (InterbankException) {
        }

        $this->assertCount(2, $calls, 'The first 2 calls should have reached the inner client');

        // Third call: the circuit should already be open.
        $this->expectException(CircuitOpenException::class);

        try {
            $breaker->execute('X', 10);
        } finally {
            $this->assertCount(2, $calls, 'With the circuit open, the inner client should NOT have been called again');
        }
    }
}
