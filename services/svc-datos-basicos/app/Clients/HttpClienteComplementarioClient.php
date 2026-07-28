<?php

namespace App\Clients;

use App\Contracts\ClienteComplementarioClient;
use App\Contracts\ClienteNoEncontradoException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use Throwable;

/**
 * Implementacion real: consulta el Sistema Complementario de Cliente por
 * HTTP. Se activa con CLIENTE_COMPLEMENTARIO_DRIVER=http y
 * CLIENTE_COMPLEMENTARIO_BASE_URL en el .env.
 */
class HttpClienteComplementarioClient implements ClienteComplementarioClient
{
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly string $baseUrl,
    ) {
    }

    public function getDetalle(string $clienteId): array
    {
        try {
            $response = $this->httpClient->request('GET', rtrim($this->baseUrl, '/') . "/clientes/{$clienteId}/detalle");
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                throw new ClienteNoEncontradoException("El Sistema Complementario no tiene registrado al cliente [{$clienteId}].", previous: $e);
            }

            throw $e;
        } catch (Throwable $e) {
            throw new ClienteNoEncontradoException("No se pudo consultar el Sistema Complementario: {$e->getMessage()}", previous: $e);
        }

        return json_decode((string) $response->getBody(), true);
    }
}
