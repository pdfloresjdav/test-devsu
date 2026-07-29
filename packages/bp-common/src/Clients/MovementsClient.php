<?php

namespace BP\Common\Clients;

interface MovementsClient
{
    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws UpstreamServiceException
     */
    public function list(string $accountId, string $bearerToken, int $limit = 20): array;
}
