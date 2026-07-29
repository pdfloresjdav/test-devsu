<?php

namespace BP\Common\Clients;

interface TransfersClient
{
    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     *
     * @throws UpstreamServiceException
     */
    public function create(array $payload, string $idempotencyKey, string $bearerToken): array;
}
