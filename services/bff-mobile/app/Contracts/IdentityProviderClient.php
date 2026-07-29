<?php

namespace App\Contracts;

interface IdentityProviderClient
{
    /**
     * Creates the new customer's identity in the identity provider
     * (sequence diagram 8.2: "BFF->>IdP: Create user identity -Management
     * API-"). Registering the access credential itself -username/password
     * or WebAuthn- is done by the app directly against the IdP after this,
     * not the BFF.
     *
     * @return array{user_id: string}
     */
    public function createUser(string $customerId, string $name, string $email): array;
}
