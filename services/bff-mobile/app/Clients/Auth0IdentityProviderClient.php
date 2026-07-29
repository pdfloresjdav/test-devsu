<?php

namespace App\Clients;

use App\Contracts\IdentityProviderClient;
use GuzzleHttp\ClientInterface;
use RuntimeException;
use Throwable;

/**
 * Real implementation against the Auth0 Management API (decision 3.5).
 * Requires an M2M client (client_credentials) with the create:users scope,
 * configured separately from the public client used by the SPA/App
 * (bp-web). Not tested against a real tenant.
 */
class Auth0IdentityProviderClient implements IdentityProviderClient
{
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly string $managementApiUrl,
        private readonly string $managementToken,
    ) {
    }

    public function createUser(string $customerId, string $name, string $email): array
    {
        try {
            $response = $this->httpClient->request('POST', rtrim($this->managementApiUrl, '/') . '/api/v2/users', [
                'headers' => ['Authorization' => "Bearer {$this->managementToken}"],
                'json' => [
                    'connection' => 'Username-Password-Authentication',
                    'email' => $email,
                    'name' => $name,
                    'app_metadata' => ['customer_id' => $customerId],
                    'password' => bin2hex(random_bytes(16)) . 'Aa1!', // replaced once the customer registers their credential
                ],
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException("Could not create the user in Auth0: {$e->getMessage()}", previous: $e);
        }

        $body = json_decode((string) $response->getBody(), true);

        return ['user_id' => $body['user_id']];
    }
}
