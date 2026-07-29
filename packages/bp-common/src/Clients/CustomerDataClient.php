<?php

namespace BP\Common\Clients;

interface CustomerDataClient
{
    /**
     * @return array<string, mixed>
     *
     * @throws UpstreamServiceException
     */
    public function getCustomer(string $customerId, string $bearerToken): array;
}
