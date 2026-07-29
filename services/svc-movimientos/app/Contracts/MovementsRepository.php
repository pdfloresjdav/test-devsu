<?php

namespace App\Contracts;

interface MovementsRepository
{
    /**
     * Movement history for an account, most recent first.
     *
     * @return array<int, array{
     *     movement_id: string,
     *     account_id: string,
     *     type: string,
     *     amount: float,
     *     description: string,
     *     date: string
     * }>
     */
    public function list(string $accountId, int $limit = 20): array;

    /**
     * Records a new movement and returns the full record (including the
     * assigned id and date).
     *
     * @return array{
     *     movement_id: string,
     *     account_id: string,
     *     type: string,
     *     amount: float,
     *     description: string,
     *     date: string
     * }
     */
    public function register(string $accountId, string $type, float $amount, string $description): array;
}
