<?php

namespace App\Services;

use App\Contracts\DeliveryTracker;
use App\Contracts\NotificationDeliveryException;
use RuntimeException;

/**
 * Translates a domain event into one or more notifications: decides the
 * channel(s) (ChannelRouter), generates the content (TemplateEngine), sends
 * them (NotificationChannel) and records the result (DeliveryTracker).
 * Kept separate from the SQS consumer so it can be tested without a real
 * queue.
 */
class NotificationEventProcessor
{
    public function __construct(
        private readonly ChannelRouter $router,
        private readonly TemplateEngine $templates,
        private readonly NotificationChannelFactory $channels,
        private readonly DeliveryTracker $tracker,
    ) {}

    /**
     * @param  array<string, mixed>  $eventBridgeEvent
     */
    public function process(array $eventBridgeEvent): void
    {
        $action = $eventBridgeEvent['detail-type'] ?? null;
        $detail = $eventBridgeEvent['detail'] ?? null;

        if (! is_string($action) || ! is_array($detail)) {
            throw new RuntimeException('Invalid event: missing detail-type or detail.');
        }

        $actor = $detail['actor'] ?? 'system';
        ['subject' => $subject, 'body' => $body] = $this->templates->render($action, $detail);

        foreach ($this->router->channelsFor($action) as $channel) {
            $this->sendByChannel($channel, $actor, $action, $subject, $body);
        }
    }

    private function sendByChannel(string $channel, string $actor, string $action, string $subject, string $body): void
    {
        try {
            $this->channels->make($channel)->send($actor, $subject, $body);
            $this->tracker->register($actor, $channel, $action, 'sent');
        } catch (NotificationDeliveryException $e) {
            $this->tracker->register($actor, $channel, $action, 'failed', $e->getMessage());
        }
    }
}
