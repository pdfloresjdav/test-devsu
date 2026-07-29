<?php

namespace App\Services;

use App\Contracts\AuditRepository;
use RuntimeException;

/**
 * Translates a domain event (the "detail" of an EventBridge event) into an
 * audit record, persists it, and archives it. Kept separate from the SQS
 * consumer so it can be tested without depending on a real queue.
 */
class AuditEventProcessor
{
    public function __construct(
        private readonly AuditRepository $repository,
        private readonly WormArchiver $archiver,
    ) {
    }

    /**
     * @param array<string, mixed> $eventBridgeEvent Full event as delivered by EventBridge/SQS.
     */
    public function process(array $eventBridgeEvent): void
    {
        $action = $eventBridgeEvent['detail-type'] ?? null;
        $detail = $eventBridgeEvent['detail'] ?? null;

        if (! is_string($action) || ! is_array($detail)) {
            throw new RuntimeException('Invalid event: missing detail-type or detail.');
        }

        $actor = $detail['actor'] ?? 'system';

        $record = $this->repository->register($actor, $action, $detail);

        $this->archiver->archive($record);
    }
}
