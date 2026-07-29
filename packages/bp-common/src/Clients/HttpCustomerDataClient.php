<?php

namespace BP\Common\Clients;

use GuzzleHttp\ClientInterface;

class HttpCustomerDataClient extends HttpUpstreamClient implements CustomerDataClient
{
    public function __construct(ClientInterface $httpClient, private readonly string $baseUrl)
    {
        parent::__construct($httpClient);
    }

    public function getCustomer(string $customerId, string $bearerToken): array
    {
        $response = $this->request('GET', rtrim($this->baseUrl, '/') . "/customers/{$customerId}", [
            'headers' => $this->authHeader($bearerToken),
        ]);

        return $this->decode($response);
    }
}
