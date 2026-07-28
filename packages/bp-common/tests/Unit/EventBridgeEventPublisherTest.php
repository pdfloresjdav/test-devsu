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

    public function test_publica_un_evento_correctamente(): void
    {
        $client = Mockery::mock(EventBridgeClient::class);
        $client->shouldReceive('putEvents')
            ->once()
            ->with(Mockery::on(function (array $args) {
                $entry = $args['Entries'][0];

                return $entry['Source'] === 'bp.services'
                    && $entry['DetailType'] === 'MovementRegistered'
                    && $entry['EventBusName'] === 'bp-domain-events'
                    && json_decode($entry['Detail'], true) === ['cuenta_id' => '1001'];
            }))
            ->andReturn(new Result(['FailedEntryCount' => 0, 'Entries' => [['EventId' => 'abc']]]));

        $publisher = new EventBridgeEventPublisher($client, 'bp-domain-events', 'bp.services');

        $publisher->publish('MovementRegistered', ['cuenta_id' => '1001']);

        $this->addToAssertionCount(1);
    }

    public function test_lanza_excepcion_si_eventbridge_rechaza_el_evento(): void
    {
        $client = Mockery::mock(EventBridgeClient::class);
        $client->shouldReceive('putEvents')->once()->andReturn(new Result([
            'FailedEntryCount' => 1,
            'Entries' => [['ErrorMessage' => 'bus no existe']],
        ]));

        $publisher = new EventBridgeEventPublisher($client, 'bp-domain-events', 'bp.services');

        $this->expectException(EventPublishingException::class);
        $this->expectExceptionMessage('bus no existe');
        $publisher->publish('MovementRegistered', ['cuenta_id' => '1001']);
    }

    public function test_lanza_excepcion_si_el_cliente_falla(): void
    {
        $client = Mockery::mock(EventBridgeClient::class);
        $client->shouldReceive('putEvents')->once()->andThrow(new \RuntimeException('timeout'));

        $publisher = new EventBridgeEventPublisher($client, 'bp-domain-events', 'bp.services');

        $this->expectException(EventPublishingException::class);
        $publisher->publish('MovementRegistered', ['cuenta_id' => '1001']);
    }
}
