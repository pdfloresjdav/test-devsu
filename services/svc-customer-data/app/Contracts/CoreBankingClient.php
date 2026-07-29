<?php

namespace App\Contracts;

interface CoreBankingClient
{
    /**
     * Basic customer data, their products, and a movements summary, as
     * exposed by the Core Banking system (the official system of record).
     *
     * @return array{
     *     customer_id: string,
     *     name: string,
     *     document: string,
     *     products: array<int, array{type: string, number: string, status: string}>
     * }
     */
    public function getBasicData(string $customerId): array;
}
