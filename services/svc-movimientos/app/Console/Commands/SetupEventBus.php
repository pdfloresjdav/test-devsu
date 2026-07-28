<?php

namespace App\Console\Commands;

use Aws\EventBridge\EventBridgeClient;
use Aws\EventBridge\Exception\EventBridgeException;
use Illuminate\Console\Command;

class SetupEventBus extends Command
{
    protected $signature = 'events:setup-bus';

    protected $description = 'Crea el bus de EventBridge de dominio si no existe (idempotente)';

    public function handle(EventBridgeClient $client): int
    {
        $busName = config('bp-common.events.event_bus_name');

        try {
            $client->createEventBus(['Name' => $busName]);
            $this->info("Bus [{$busName}] creado.");
        } catch (EventBridgeException $e) {
            if ($e->getAwsErrorCode() !== 'ResourceAlreadyExistsException') {
                throw $e;
            }

            $this->info("El bus [{$busName}] ya existia.");
        }

        return self::SUCCESS;
    }
}
