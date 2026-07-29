<?php

namespace App\Contracts;

interface DeliveryTracker
{
    /**
     * Records the outcome of a delivery attempt -- evidence that the
     * customer was notified (or that it failed), required by the standard.
     */
    public function register(string $actor, string $channel, string $action, string $status, ?string $failureReason = null): void;
}
