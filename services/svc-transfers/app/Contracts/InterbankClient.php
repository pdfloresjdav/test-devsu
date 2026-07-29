<?php

namespace App\Contracts;

interface InterbankClient
{
    /**
     * Executes the transfer on the interbank network / destination bank.
     *
     * @return array{confirmation_id: string}
     *
     * @throws InterbankException if the destination bank rejects it or doesn't respond.
     */
    public function execute(string $destinationAccount, float $amount): array;
}
