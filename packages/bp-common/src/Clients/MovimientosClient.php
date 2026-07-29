<?php

namespace BP\Common\Clients;

interface MovimientosClient
{
    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws UpstreamServiceException
     */
    public function listar(string $cuentaId, string $bearerToken, int $limit = 20): array;
}
