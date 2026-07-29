<?php

namespace App\Clients;

use App\Contracts\CoreBankingClient;
use App\Contracts\CustomerNotFoundException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use Throwable;

/**
 * Real implementation: queries Core Banking over HTTP. Activated with
 * CORE_BANKING_DRIVER=http and CORE_BANKING_BASE_URL in .env.
 */
class HttpCoreBankingClient implements CoreBankingClient
{
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly string $baseUrl,
    ) {
    }

    public function getBasicData(string $customerId): array
    {
        try {
            $response = $this->httpClient->request('GET', rtrim($this->baseUrl, '/') . "/customers/{$customerId}");
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                throw new CustomerNotFoundException("Core Banking has no record of customer [{$customerId}].", previous: $e);
            }

            throw $e;
        } catch (Throwable $e) {
            throw new CustomerNotFoundException("Could not query Core Banking: {$e->getMessage()}", previous: $e);
        }

        return json_decode((string) $response->getBody(), true);
    }
}
