<?php

namespace App\Clients;

use App\Contracts\InterbankClient;
use App\Contracts\InterbankException;
use GuzzleHttp\ClientInterface;
use Throwable;

/**
 * Real implementation: calls the interbank network/switch over HTTP (mTLS
 * in production, terminated at the outbound load balancer/gateway).
 * Activated with INTERBANK_DRIVER=http + INTERBANK_BASE_URL in .env.
 */
class HttpInterbankClient implements InterbankClient
{
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly string $baseUrl,
    ) {}

    public function execute(string $destinationAccount, float $amount): array
    {
        try {
            $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/').'/transfer', [
                'json' => ['destination_account' => $destinationAccount, 'amount' => $amount],
            ]);
        } catch (Throwable $e) {
            throw new InterbankException("The destination bank did not respond: {$e->getMessage()}", previous: $e);
        }

        return json_decode((string) $response->getBody(), true);
    }
}
