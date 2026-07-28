<?php

namespace App\Contracts;

interface DatosBasicosClient
{
    /**
     * @return array<string, mixed>
     *
     * @throws UpstreamServiceException
     */
    public function obtenerCliente(string $clienteId, string $bearerToken): array;
}
