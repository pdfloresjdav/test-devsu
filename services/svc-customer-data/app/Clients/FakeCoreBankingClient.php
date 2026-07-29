<?php

namespace App\Clients;

use App\Contracts\CoreBankingClient;
use App\Contracts\CustomerNotFoundException;

/**
 * In-memory fixture that simulates Core Banking while no real integration
 * exists. It's replaced by HttpCoreBankingClient by setting
 * CORE_BANKING_DRIVER=http in .env, without touching the rest of the domain.
 */
class FakeCoreBankingClient implements CoreBankingClient
{
    /** @var array<string, array<string, mixed>> */
    private array $customers = [
        '1001' => [
            'customer_id' => '1001',
            'name' => 'Ana Torres',
            'document' => '1234567890',
            'products' => [
                ['type' => 'savings_account', 'number' => '0011-2233', 'status' => 'active'],
                ['type' => 'debit_card', 'number' => '4455-6677', 'status' => 'active'],
            ],
        ],
        '1002' => [
            'customer_id' => '1002',
            'name' => 'Luis Ramirez',
            'document' => '0987654321',
            'products' => [
                ['type' => 'checking_account', 'number' => '9988-7766', 'status' => 'active'],
            ],
        ],
    ];

    public function getBasicData(string $customerId): array
    {
        if (! isset($this->customers[$customerId])) {
            throw new CustomerNotFoundException("Core Banking has no record of customer [{$customerId}].");
        }

        return $this->customers[$customerId];
    }
}
