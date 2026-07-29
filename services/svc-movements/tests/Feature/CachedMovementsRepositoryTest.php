<?php

namespace Tests\Feature;

use App\Contracts\MovementsRepository;
use App\Repositories\CachedMovementsRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class CachedMovementsRepositoryTest extends TestCase
{
    private function countingInnerRepository(array &$calls): MovementsRepository
    {
        return new class($calls) implements MovementsRepository
        {
            public function __construct(private array &$calls) {}

            public function list(string $accountId, int $limit = 20): array
            {
                $this->calls[] = $accountId;

                return [['movement_id' => 'm1', 'account_id' => $accountId, 'type' => 'debit', 'amount' => 10.0, 'description' => 'x', 'date' => 'now']];
            }

            public function register(string $accountId, string $type, float $amount, string $description): array
            {
                return ['movement_id' => 'new', 'account_id' => $accountId, 'type' => $type, 'amount' => $amount, 'description' => $description, 'date' => 'now'];
            }
        };
    }

    public function test_the_second_read_does_not_hit_the_inner_repository_again(): void
    {
        $accountId = (string) Str::uuid();
        $calls = [];
        $repo = new CachedMovementsRepository($this->countingInnerRepository($calls), Cache::store('redis'), 60);

        $repo->list($accountId);
        $repo->list($accountId);

        $this->assertCount(1, $calls, 'The inner repository should have only been called once (the second read should have come from Redis)');
    }

    public function test_register_invalidates_the_accounts_cache(): void
    {
        $accountId = (string) Str::uuid();
        $calls = [];
        $repo = new CachedMovementsRepository($this->countingInnerRepository($calls), Cache::store('redis'), 60);

        $repo->list($accountId);
        $repo->register($accountId, 'debit', 50.0, 'purchase');
        $repo->list($accountId);

        $this->assertCount(2, $calls, 'After registering a movement, the next read should have been a miss (cache invalidated) and hit the inner repository again');
    }
}
