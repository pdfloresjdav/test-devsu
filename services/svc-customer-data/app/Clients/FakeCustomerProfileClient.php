<?php

namespace App\Clients;

use App\Contracts\CustomerNotFoundException;
use App\Contracts\CustomerProfileClient;

/**
 * In-memory fixture that simulates the Complementary Customer System.
 * It's replaced by HttpCustomerProfileClient by setting
 * CUSTOMER_PROFILE_DRIVER=http in .env.
 */
class FakeCustomerProfileClient implements CustomerProfileClient
{
    /** @var array<string, array<string, mixed>> */
    private array $profiles = [
        '1001' => [
            'customer_id' => '1001',
            'segment' => 'preferred',
            'email' => 'ana.torres@example.com',
            'phone' => '+57 300 111 2233',
            'preferences' => ['language' => 'es', 'notification_channel' => 'push'],
        ],
        '1002' => [
            'customer_id' => '1002',
            'segment' => 'standard',
            'email' => 'luis.ramirez@example.com',
            'phone' => '+57 300 444 5566',
            'preferences' => ['language' => 'es', 'notification_channel' => 'email'],
        ],
    ];

    public function getProfile(string $customerId): array
    {
        if (! isset($this->profiles[$customerId])) {
            throw new CustomerNotFoundException("The Complementary Customer System has no record of customer [{$customerId}].");
        }

        return $this->profiles[$customerId];
    }
}
