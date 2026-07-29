<?php

namespace App\Services;

use BP\Common\Clients\CustomerDataClient;
use BP\Common\Clients\MovementsClient;

/**
 * The only BFF endpoint that actually COMPOSES data from more than one
 * business service into a single contract for the SPA (the other two
 * endpoints are adapted pass-through). If either call fails, the failure
 * propagates as-is -- there is no point showing a half dashboard with the
 * balance/customer but unable to confirm the movement history is correct,
 * or vice versa.
 */
class DashboardService
{
    public function __construct(
        private readonly CustomerDataClient $customerData,
        private readonly MovementsClient $movements,
    ) {
    }

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
