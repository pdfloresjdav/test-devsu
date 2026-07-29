<?php

namespace BP\Common\Tests\Unit;

use Aws\EventBridge\EventBridgeClient;
use Aws\Result;
use BP\Common\Events\EventBridgeEventPublisher;
use BP\Common\Events\EventPublishingException;
use Mockery;
use PHPUnit\Framework\TestCase;

class EventBridgeEventPublisherTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_publishes_an_event_correctly(): void
    {
        $client = Mockery::mock(EventBridgeClient::class);
        $client->shouldReceive('putEvents')
            ->once()
            ->with(Mockery::on(function (array $args) {
                $entry = $args['Entries'][0];

                return $entry['Source'] === 'bp.services'
                    && $entry['DetailType'] === 'MovementRegistered'
                    && $entry['EventBusName'] === 'bp-domain-events'
                    && json_decode($entry['Detail'], true) === ['account_id' => '1001'];
            }))
            ->andReturn(new Result(['FailedEntryCount' => 0, 'Entries' => [['EventId' => 'abc']]]));

        $publisher = new EventBridgeEventPublisher($client, 'bp-domain-events', 'bp.services');

        $publisher->publish('MovementRegistered', ['account_id' => '1001']);

        $this->addToAssertionCount(1);
    }

    public function test_throws_an_exception_if_eventbridge_rejects_the_event(): void
    {
        $client = Mockery::mock(EventBridgeClient::class);
        $client->shouldReceive('putEvents')->once()->andReturn(new Result([
            'FailedEntryCount' => 1,
            'Entries' => [['ErrorMessage' => 'bus does not exist']],
        ]));

        $publisher = new EventBridgeEventPublisher($client, 'bp-domain-events', 'bp.services');

        $this->expectException(EventPublishingException::class);
        $this->expectExceptionMessage('bus does not exist');
        $publisher->publish('MovementRegistered', ['account_id' => '1001']);
    }

    public function test_throws_an_exception_if_the_client_fails(): void
    {
        $client = Mockery::mock(EventBridgeClient::class);
        $client->shouldReceive('putEvents')->once()->andThrow(new \RuntimeException('timeout'));

        $publisher = new EventBridgeEventPublisher($client, 'bp-domain-events', 'bp.services');

        $this->expectException(EventPublishingException::class);
        $publisher->publish('MovementRegistered', ['account_id' => '1001']);
    }
}
