<?php

namespace App\Contracts;

interface TransferenciasClient
{
    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     *
     * @throws UpstreamServiceException
     */
    public function crear(array $payload, string $idempotencyKey, string $bearerToken): array;
}
