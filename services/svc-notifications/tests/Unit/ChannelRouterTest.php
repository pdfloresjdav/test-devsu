<?php

namespace Tests\Unit;

use App\Services\ChannelRouter;
use Tests\TestCase;

class ChannelRouterTest extends TestCase
{
    public function test_movement_registered_only_goes_through_push(): void
    {
        $this->assertSame(['push'], (new ChannelRouter)->channelsFor('MovementRegistered'));
    }

    public function test_transfer_failed_goes_through_push_and_email(): void
    {
        $this->assertSame(['push', 'email'], (new ChannelRouter)->channelsFor('TransferFailed'));
    }

    public function test_an_unknown_event_uses_the_default_channel(): void
    {
        $this->assertSame(['push'], (new ChannelRouter)->channelsFor('EventThatDoesNotExist'));
    }
}
