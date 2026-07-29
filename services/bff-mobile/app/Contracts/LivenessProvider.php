<?php

namespace App\Contracts;

interface LivenessProvider
{
    /**
     * Revalidates that whoever is operating is still the same customer
     * verified during onboarding -- step-up check for sensitive operations
     * (sequence diagram 8.2, decision 3.7).
     *
     * @return array{approved: bool, score: float}
     */
    public function revalidate(string $referenceSelfie, string $newSelfie): array;
}
