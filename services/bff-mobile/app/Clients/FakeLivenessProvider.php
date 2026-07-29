<?php

namespace App\Clients;

use App\Contracts\LivenessProvider;

/**
 * Simulates AWS Rekognition Face Liveness while testing locally.
 * Deterministic: if the new selfie is literally "REJECT", the revalidation
 * fails; any other value is approved.
 */
class FakeLivenessProvider implements LivenessProvider
{
    public function revalidate(string $referenceSelfie, string $newSelfie): array
    {
        if ($newSelfie === 'REJECT') {
            return ['approved' => false, 'score' => 0.30];
        }

        return ['approved' => true, 'score' => 0.95];
    }
}
