<?php

namespace BP\Common\Clients;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Common base for the BFFs' (Web and Mobile) clients toward the business
 * microservices: makes the HTTP call and translates any failure into an
 * UpstreamServiceException preserving the real status code from the
 * service (404, 422, etc.) instead of flattening everything into a
 * generic error.
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
            $message = $body['error']['message'] ?? $e->getMessage();
            $code = $body['error']['code'] ?? null;

            throw new UpstreamServiceException($message, $status, $code, $e);
        } catch (GuzzleException|Throwable $e) {
            throw new UpstreamServiceException("Could not reach the service: {$e->getMessage()}", 502, previous: $e);
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
