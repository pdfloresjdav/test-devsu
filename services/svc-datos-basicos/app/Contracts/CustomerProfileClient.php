<?php

namespace App\Contracts;

interface CustomerProfileClient
{
    /**
     * Detailed/extended customer information that doesn't live in the
     * Core (e.g. commercial segment, preferences, extended contact info).
     *
     * @return array{
     *     customer_id: string,
     *     segment: string,
     *     email: string,
     *     phone: string,
     *     preferences: array<string, mixed>
     * }
     */
    public function getProfile(string $customerId): array;
}
