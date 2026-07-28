<?php

namespace App\Clients;

use App\Contracts\ClienteNoEncontradoException;
use App\Contracts\CoreBancarioClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use Throwable;

/**
 * Implementacion real: consulta el Core Bancario por HTTP. Se activa con
 * CORE_BANCARIO_DRIVER=http y CORE_BANCARIO_BASE_URL en el .env.
 */
class HttpCoreBancarioClient implements CoreBancarioClient
{
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly string $baseUrl,
    ) {
    }

    public function getDatosBasicos(string $clienteId): array
    {
        try {
            $response = $this->httpClient->request('GET', rtrim($this->baseUrl, '/') . "/clientes/{$clienteId}");
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                throw new ClienteNoEncontradoException("El Core Bancario no tiene registrado al cliente [{$clienteId}].", previous: $e);
            }

            throw $e;
        } catch (Throwable $e) {
            throw new ClienteNoEncontradoException("No se pudo consultar el Core Bancario: {$e->getMessage()}", previous: $e);
        }

        return json_decode((string) $response->getBody(), true);
    }
}
