<?php

namespace BP\Common\Clients;

use GuzzleHttp\ClientInterface;

class HttpTransfersClient extends HttpUpstreamClient implements TransfersClient
{
    public function __construct(ClientInterface $httpClient, private readonly string $baseUrl)
    {
        parent::__construct($httpClient);
    }

    public function create(array $payload, string $idempotencyKey, string $bearerToken): array
    {
        $response = $this->request('POST', rtrim($this->baseUrl, '/').'/transfers', [
            'headers' => $this->authHeader($bearerToken) + ['Idempotency-Key' => $idempotencyKey],
            'json' => $payload,
        ]);

        return $this->decode($response);
    }
}
