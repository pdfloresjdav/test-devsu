<?php

namespace App\Clients;

use App\Contracts\UpstreamServiceException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Base comun para los clientes hacia los microservicios de negocio: hace
 * la llamada HTTP y traduce cualquier falla a UpstreamServiceException
 * preservando el status code real del servicio (404, 422, etc.) en vez de
 * aplanar todo a un error generico.
 */
abstract class HttpUpstreamClient
{
    public function __construct(protected readonly ClientInterface $httpClient)
    {
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function request(string $method, string $uri, array $options = []): ResponseInterface
    {
        try {
            return $this->httpClient->request($method, $uri, $options);
        } catch (BadResponseException $e) {
            $status = $e->getResponse()->getStatusCode();
            $body = json_decode((string) $e->getResponse()->getBody(), true);
            $mensaje = $body['error']['message'] ?? $e->getMessage();

            throw new UpstreamServiceException($mensaje, $status, $e);
        } catch (GuzzleException|Throwable $e) {
            throw new UpstreamServiceException("No se pudo contactar el servicio: {$e->getMessage()}", 502, $e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function decode(ResponseInterface $response): array
    {
        $decoded = json_decode((string) $response->getBody(), true);

        return $decoded['data'] ?? $decoded ?? [];
    }

    protected function authHeader(string $bearerToken): array
    {
        return ['Authorization' => "Bearer {$bearerToken}"];
    }
}
