<?php

namespace App\Services;

use BP\Common\Clients\CustomerDataClient;
use BP\Common\Clients\MovementsClient;

class DashboardService
{
    public function __construct(
        private readonly CustomerDataClient $customerData,
        private readonly MovementsClient $movements,
    ) {}

    /**
     * @return array{customer: array<string, mixed>, recent_movements: array<int, array<string, mixed>>}
     */
    public function get(string $accountId, string $bearerToken): array
    {
        return [
            'customer' => $this->customerData->getCustomer($accountId, $bearerToken),
            'recent_movements' => $this->movements->list($accountId, $bearerToken, limit: 10),
        ];
    }
}
