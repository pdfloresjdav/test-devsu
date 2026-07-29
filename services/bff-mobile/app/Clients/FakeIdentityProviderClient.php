<?php

namespace App\Clients;

use App\Contracts\IdentityProviderClient;
use Illuminate\Support\Str;

/**
 * Simulates creating a user in the identity provider. The local mock-oidc
 * (oidc-server-mock) has no real Management API -- it's a test double with
 * fixed users by configuration, not a full IdP -- so this is the only
 * viable option for local development, not just a "simpler" one.
 * Auth0IdentityProviderClient is the real implementation for once the
 * Auth0 tenant is up.
 */
class FakeIdentityProviderClient implements IdentityProviderClient
{
    public function createUser(string $customerId, string $name, string $email): array
    {
        return ['user_id' => 'auth0|fake-'.Str::uuid()];
    }
}
