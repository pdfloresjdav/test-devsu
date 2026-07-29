<?php

namespace App\Clients;

use App\Contracts\InterbankClient;
use App\Contracts\InterbankException;
use Illuminate\Support\Str;

/**
 * Simulates the interbank network while no real integration exists.
 * Deterministic so the compensation path can be tested: any destination
 * account starting with "FAIL-" is rejected by the destination bank; the
 * rest are confirmed.
 */
class FakeInterbankClient implements InterbankClient
{
    public function execute(string $destinationAccount, float $amount): array
    {
        if (str_starts_with($destinationAccount, 'FAIL-')) {
            throw new InterbankException("The destination bank rejected the transfer to [{$destinationAccount}].");
        }

        return ['confirmation_id' => (string) Str::uuid()];
    }
}
