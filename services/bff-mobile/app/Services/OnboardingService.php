<?php

namespace App\Services;

use App\Contracts\IdentityProviderClient;
use App\Contracts\KycProvider;
use App\Contracts\OnboardingRejectedException;
use BP\Common\Events\EventPublisherInterface;

/**
 * Orchestrates onboarding (sequence diagram 8.2): verifies identity with
 * the KYC provider and, if approved, creates the customer's identity in
 * the identity provider. Registering the access credential (username/
 * password or WebAuthn) is done by the app directly against the IdP after
 * this, not the BFF.
 *
 * Scope simplification versus the sequence diagram: the customer's
 * existence in Core Banking is not validated before creating the identity
 * -- that call would require the BFF to authenticate as a service
 * (client_credentials) instead of with the end user's JWT (which doesn't
 * exist yet during onboarding), a full machine-to-machine authentication
 * flow this phase's checklist doesn't ask for. Documented here so it isn't
 * lost track of if it's picked up later.
 */
class OnboardingService
{
    public function __construct(
        private readonly KycProvider $kyc,
        private readonly IdentityProviderClient $identity,
        private readonly EventPublisherInterface $events,
    ) {}

    /**
     * @return array{user_id: string, status: string}
     */
    public function onboard(
        string $customerId,
        string $name,
        string $email,
        string $identityDocument,
        string $selfie,
    ): array {
        $kycResult = $this->kyc->verify($identityDocument, $selfie);

        if (! $kycResult['approved']) {
            $this->events->publish('OnboardingRejected', [
                'actor' => $customerId,
                'customer_id' => $customerId,
                'reason' => $kycResult['reason'],
            ]);

            throw new OnboardingRejectedException($kycResult['reason'] ?? 'Identity verification rejected');
        }

        $identity = $this->identity->createUser($customerId, $name, $email);

        $this->events->publish('OnboardingCompleted', [
            'actor' => $customerId,
            'customer_id' => $customerId,
            'user_id' => $identity['user_id'],
        ]);

        return ['user_id' => $identity['user_id'], 'status' => 'approved'];
    }
}
