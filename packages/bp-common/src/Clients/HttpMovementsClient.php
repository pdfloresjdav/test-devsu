<?php

namespace BP\Common\Clients;

use GuzzleHttp\ClientInterface;

class HttpMovementsClient extends HttpUpstreamClient implements MovementsClient
{
    public function __construct(ClientInterface $httpClient, private readonly string $baseUrl)
    {
        parent::__construct($httpClient);
    }

    public function list(string $accountId, string $bearerToken, int $limit = 20): array
    {
        $response = $this->request('GET', rtrim($this->baseUrl, '/')."/accounts/{$accountId}/movements", [
            'headers' => $this->authHeader($bearerToken),
            'query' => ['limit' => $limit],
        ]);

        return $this->decode($response);
    }
}
