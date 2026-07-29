<?php

namespace App\Services;

/**
 * Decides which channel(s) to notify through based on the event type
 * (decision 3.12: separate the immediate channel -push- from the backup
 * channel -email- to give real redundancy). The mapping lives in config so
 * it can be adjusted without touching code.
 */
class ChannelRouter
{
    /**
     * @return array<int, string>
     */
    public function channelsFor(string $action): array
    {
        $map = config('services.notifications.channel_map', []);

        return $map[$action] ?? $map['default'] ?? ['push'];
    }
}
