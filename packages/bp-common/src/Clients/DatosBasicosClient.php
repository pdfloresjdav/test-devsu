<?php

namespace BP\Common\Clients;

interface DatosBasicosClient
{
    /**
     * @return array<string, mixed>
     *
     * @throws UpstreamServiceException
     */
    public function obtenerCliente(string $clienteId, string $bearerToken): array;
}
