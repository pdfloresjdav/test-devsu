<?php

namespace App\Contracts;

interface IdentityProviderClient
{
    /**
     * Crea la identidad del nuevo cliente en el proveedor de identidad
     * (diagrama de secuencia 8.2: "BFF->>IdP: Crea identidad de usuario
     * -Management API-"). El registro de la credencial de acceso en si
     * -usuario/clave o WebAuthn- lo hace la app directamente contra el
     * IdP despues de esto, no el BFF.
     *
     * @return array{usuario_id: string}
     */
    public function crearUsuario(string $clienteId, string $nombre, string $email): array;
}
