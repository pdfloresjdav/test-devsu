<?php

namespace App\Clients;

use App\Contracts\TransferenciasClient;

class HttpTransferenciasClient extends HttpUpstreamClient implements TransferenciasClient
{
    public function __construct(\GuzzleHttp\ClientInterface $httpClient, private readonly string $baseUrl)
    {
        parent::__construct($httpClient);
    }

    public function crear(array $payload, string $idempotencyKey, string $bearerToken): array
    {
        $response = $this->request('POST', rtrim($this->baseUrl, '/') . '/transfers', [
            'headers' => $this->authHeader($bearerToken) + ['Idempotency-Key' => $idempotencyKey],
            'json' => $payload,
        ]);

        return $this->decode($response);
    }
}
