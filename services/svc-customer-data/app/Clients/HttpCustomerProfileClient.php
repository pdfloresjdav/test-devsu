<?php

namespace App\Clients;

use App\Contracts\CustomerNotFoundException;
use App\Contracts\CustomerProfileClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use Throwable;

/**
 * Real implementation: queries the Complementary Customer System over
 * HTTP. Activated with CUSTOMER_PROFILE_DRIVER=http and
 * CUSTOMER_PROFILE_BASE_URL in .env.
 */
class HttpCustomerProfileClient implements CustomerProfileClient
{
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly string $baseUrl,
    ) {
    }

    public function getProfile(string $customerId): array
    {
        try {
            $response = $this->httpClient->request('GET', rtrim($this->baseUrl, '/') . "/customers/{$customerId}/profile");
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                throw new CustomerNotFoundException("The Complementary Customer System has no record of customer [{$customerId}].", previous: $e);
            }

            throw $e;
        } catch (Throwable $e) {
            throw new CustomerNotFoundException("Could not query the Complementary Customer System: {$e->getMessage()}", previous: $e);
        }

        return json_decode((string) $response->getBody(), true);
    }
}
