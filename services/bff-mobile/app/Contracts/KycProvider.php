<?php

namespace App\Contracts;

interface KycProvider
{
    /**
     * Verifies the identity document + selfie with liveness proof
     * (architecture document decision 3.7).
     *
     * @return array{approved: bool, score: float, reason: ?string}
     */
    public function verify(string $identityDocument, string $selfie): array;
}
