<?php

namespace App\Contracts;

interface NotificationChannel
{
    /**
     * Sends the notification. Must throw NotificationDeliveryException if
     * the send fails, so the DeliveryTracker records the result.
     */
    public function send(string $recipient, string $subject, string $body): void;
}
