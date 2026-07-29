<?php

namespace App\Clients;

use App\Contracts\IdentityProviderClient;
use GuzzleHttp\ClientInterface;
use RuntimeException;
use Throwable;

/**
 * Implementacion real contra la Management API de Auth0 (decision 3.5).
 * Requiere un cliente M2M (client_credentials) con el scope
 * create:users, configurado por separado del cliente publico que usan
 * la SPA/App (bp-web). No se prueba contra un tenant real.
 */
class Auth0IdentityProviderClient implements IdentityProviderClient
{
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly string $managementApiUrl,
        private readonly string $managementToken,
    ) {
    }

    public function crearUsuario(string $clienteId, string $nombre, string $email): array
    {
        try {
            $response = $this->httpClient->request('POST', rtrim($this->managementApiUrl, '/') . '/api/v2/users', [
                'headers' => ['Authorization' => "Bearer {$this->managementToken}"],
                'json' => [
                    'connection' => 'Username-Password-Authentication',
                    'email' => $email,
                    'name' => $nombre,
                    'app_metadata' => ['cliente_id' => $clienteId],
                    'password' => bin2hex(random_bytes(16)) . 'Aa1!', // se reemplaza cuando el cliente registre su credencial
                ],
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException("No se pudo crear el usuario en Auth0: {$e->getMessage()}", previous: $e);
        }

        $body = json_decode((string) $response->getBody(), true);

        return ['usuario_id' => $body['user_id']];
    }
}
