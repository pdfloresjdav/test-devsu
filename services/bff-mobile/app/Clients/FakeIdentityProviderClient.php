<?php

namespace App\Clients;

use App\Contracts\IdentityProviderClient;
use Illuminate\Support\Str;

/**
 * Simula la creacion de un usuario en el proveedor de identidad. El
 * mock-oidc local (oidc-server-mock) no tiene una Management API real --
 * es un doble de pruebas con usuarios fijos por configuracion, no un IdP
 * completo -- asi que esta es la unica opcion viable para desarrollo
 * local, no solo una opcion "mas simple". Auth0IdentityProviderClient es
 * la implementacion real para cuando el tenant de Auth0 este arriba.
 */
class FakeIdentityProviderClient implements IdentityProviderClient
{
    public function crearUsuario(string $clienteId, string $nombre, string $email): array
    {
        return ['usuario_id' => 'auth0|fake-' . Str::uuid()];
    }
}
