<?php

namespace BP\Common\Clients;

use GuzzleHttp\ClientInterface;

class HttpDatosBasicosClient extends HttpUpstreamClient implements DatosBasicosClient
{
    public function __construct(ClientInterface $httpClient, private readonly string $baseUrl)
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
