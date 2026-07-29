<?php

namespace Tests\Unit;

use App\Channels\LogNotificationChannel;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LogNotificationChannelTest extends TestCase
{
    public function test_leaves_the_channel_recipient_and_content_in_the_log(): void
    {
        Log::spy();

        (new LogNotificationChannel('push'))->send('user-abc', 'Test subject', 'Test body');

        Log::shouldHaveReceived('info')->once()->withArgs(function (string $message, array $context) {
            return $context['channel'] === 'push'
                && $context['recipient'] === 'user-abc'
                && $context['subject'] === 'Test subject'
                && $context['body'] === 'Test body';
        });
    }
}
