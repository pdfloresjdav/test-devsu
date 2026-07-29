<?php

namespace BP\Common\Clients;

use GuzzleHttp\ClientInterface;

class HttpMovimientosClient extends HttpUpstreamClient implements MovimientosClient
{
    public function __construct(ClientInterface $httpClient, private readonly string $baseUrl)
    {
        parent::__construct($httpClient);
    }

    public function listar(string $cuentaId, string $bearerToken, int $limit = 20): array
    {
        $response = $this->request('GET', rtrim($this->baseUrl, '/') . "/cuentas/{$cuentaId}/movimientos", [
            'headers' => $this->authHeader($bearerToken),
            'query' => ['limit' => $limit],
        ]);

        return $this->decode($response);
    }
}
