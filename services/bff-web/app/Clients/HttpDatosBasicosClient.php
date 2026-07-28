<?php

namespace App\Clients;

use App\Contracts\DatosBasicosClient;

class HttpDatosBasicosClient extends HttpUpstreamClient implements DatosBasicosClient
{
    public function __construct(\GuzzleHttp\ClientInterface $httpClient, private readonly string $baseUrl)
    {
        parent::__construct($httpClient);
    }

    public function obtenerCliente(string $clienteId, string $bearerToken): array
    {
        $response = $this->request('GET', rtrim($this->baseUrl, '/') . "/clientes/{$clienteId}", [
            'headers' => $this->authHeader($bearerToken),
        ]);

        return $this->decode($response);
    }
}
