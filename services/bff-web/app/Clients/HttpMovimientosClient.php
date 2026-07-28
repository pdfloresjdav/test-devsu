<?php

namespace App\Clients;

use App\Contracts\MovimientosClient;

class HttpMovimientosClient extends HttpUpstreamClient implements MovimientosClient
{
    public function __construct(\GuzzleHttp\ClientInterface $httpClient, private readonly string $baseUrl)
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
