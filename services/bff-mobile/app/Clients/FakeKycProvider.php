<?php

namespace App\Clients;

use App\Contracts\KycProvider;

/**
 * Simulates the KYC provider (Onfido/iProov) while there's no real
 * integration. Deterministic so the rejection path can be tested: any
 * identity document starting with "REJECT-" is rejected; everything else
 * is approved.
 */
class FakeKycProvider implements KycProvider
{
    public function verify(string $identityDocument, string $selfie): array
    {
        if (str_starts_with($identityDocument, 'REJECT-')) {
            return ['approved' => false, 'score' => 0.12, 'reason' => 'The document does not match the selfie'];
        }

        return ['approved' => true, 'score' => 0.97, 'reason' => null];
    }
}
