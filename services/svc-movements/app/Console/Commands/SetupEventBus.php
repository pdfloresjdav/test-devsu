<?php

namespace App\Console\Commands;

use Aws\EventBridge\EventBridgeClient;
use Aws\EventBridge\Exception\EventBridgeException;
use Illuminate\Console\Command;

class SetupEventBus extends Command
{
    protected $signature = 'events:setup-bus';

    protected $description = 'Creates the domain EventBridge bus if it does not exist (idempotent)';

    public function handle(EventBridgeClient $client): int
    {
        $busName = config('bp-common.events.event_bus_name');

        try {
            $client->createEventBus(['Name' => $busName]);
            $this->info("Bus [{$busName}] created.");
        } catch (EventBridgeException $e) {
            if ($e->getAwsErrorCode() !== 'ResourceAlreadyExistsException') {
                throw $e;
            }

            $this->info("Bus [{$busName}] already existed.");
        }

        return self::SUCCESS;
    }
}
