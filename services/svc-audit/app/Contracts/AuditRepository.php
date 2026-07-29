<?php

namespace App\Contracts;

interface AuditRepository
{
    /**
     * Persists an immutable audit record (actor, action, detail,
     * timestamp, and event hash) -- "Audit database" decision from the
     * architecture document.
     *
     * @param  array<string, mixed>  $detail
     * @return array{
     *     audit_id: string,
     *     actor: string,
     *     action: string,
     *     detail: array<string, mixed>,
     *     hash: string,
     *     timestamp: string
     * }
     */
    public function register(string $actor, string $action, array $detail): array;
}
