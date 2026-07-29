<?php

namespace Tests\Unit;

use App\Contracts\DeliveryTracker;
use App\Contracts\NotificationChannel;
use App\Contracts\NotificationDeliveryException;
use App\Services\ChannelRouter;
use App\Services\NotificationChannelFactory;
use App\Services\NotificationEventProcessor;
use App\Services\TemplateEngine;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class NotificationEventProcessorTest extends TestCase
{
    public function test_sends_through_each_channel_and_records_success(): void
    {
        $router = $this->createMock(ChannelRouter::class);
        $router->method('channelsFor')->with('TransferFailed')->willReturn(['push', 'email']);

        $templates = $this->createMock(TemplateEngine::class);
        $templates->method('render')->willReturn(['subject' => 'Subject', 'body' => 'Body']);

        $channel = $this->createMock(NotificationChannel::class);
        $channel->expects($this->exactly(2))->method('send')->with('user-abc', 'Subject', 'Body');

        $factory = $this->createMock(NotificationChannelFactory::class);
        $factory->method('make')->willReturn($channel);

        $tracker = $this->createMock(DeliveryTracker::class);
        $tracker->expects($this->exactly(2))->method('register')->with('user-abc', $this->isType('string'), 'TransferFailed', 'sent');

        $processor = new NotificationEventProcessor($router, $templates, $factory, $tracker);

        $processor->process([
            'detail-type' => 'TransferFailed',
            'detail' => ['actor' => 'user-abc', 'amount' => 100],
        ]);
    }

    public function test_records_failed_if_the_channel_could_not_deliver(): void
    {
        $router = $this->createMock(ChannelRouter::class);
        $router->method('channelsFor')->willReturn(['push']);

        $templates = $this->createMock(TemplateEngine::class);
        $templates->method('render')->willReturn(['subject' => 'Subject', 'body' => 'Body']);

        $channel = $this->createMock(NotificationChannel::class);
        $channel->method('send')->willThrowException(new NotificationDeliveryException('provider down'));

        $factory = $this->createMock(NotificationChannelFactory::class);
        $factory->method('make')->willReturn($channel);

        $tracker = $this->createMock(DeliveryTracker::class);
        $tracker->expects($this->once())->method('register')->with('user-abc', 'push', 'MovementRegistered', 'failed', 'provider down');

        $processor = new NotificationEventProcessor($router, $templates, $factory, $tracker);

        $processor->process([
            'detail-type' => 'MovementRegistered',
            'detail' => ['actor' => 'user-abc'],
        ]);
    }

    public function test_rejects_an_event_without_detail(): void
    {
        $processor = new NotificationEventProcessor(
            $this->createMock(ChannelRouter::class),
            $this->createMock(TemplateEngine::class),
            $this->createMock(NotificationChannelFactory::class),
            $this->createMock(DeliveryTracker::class),
        );

        $this->expectException(RuntimeException::class);
        $processor->process(['detail-type' => 'MovementRegistered']);
    }
}
