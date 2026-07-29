<?php

namespace App\Services;

use App\Contracts\CoreBankingClient;
use App\Contracts\CustomerProfileClient;

/**
 * API Composition pattern: combines the Core Banking response and the
 * Complementary Customer System response into a single output contract,
 * so the BFF doesn't have to orchestrate two calls or know there are two
 * different sources.
 */
class CustomerCompositionService
{
    public function __construct(
        private readonly CoreBankingClient $coreBanking,
        private readonly CustomerProfileClient $customerProfile,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function compose(string $customerId): array
    {
        $basicData = $this->coreBanking->getBasicData($customerId);
        $profile = $this->customerProfile->getProfile($customerId);

        return [
            'customer_id' => $customerId,
            'name' => $basicData['name'],
            'document' => $basicData['document'],
            'products' => $basicData['products'],
            'segment' => $profile['segment'],
            'contact' => [
                'email' => $profile['email'],
                'phone' => $profile['phone'],
            ],
            'preferences' => $profile['preferences'],
        ];
    }
}
