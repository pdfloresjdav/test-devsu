<?php

namespace App\Console\Commands;

use App\Services\NotificationEventProcessor;
use Aws\Sqs\SqsClient;
use Illuminate\Console\Command;
use Throwable;

/**
 * Consumer for Notifications' SQS queue (the same domain events as Audit,
 * but with its OWN EventBridge queue/rule -- Pub/Sub with Competing
 * Consumers pattern, decision 3.13: each consumer has its own independent
 * copy of every event). See the note about Horizon/pure-worker in
 * CLAUDE.md and in svc-auditoria.
 */
class ConsumeNotificationEvents extends Command
{
    protected $signature = 'notifications:consume {--once : Process a single receive cycle and exit (for tests/debugging)}';

    protected $description = 'Consumes domain events from SQS and dispatches the corresponding notifications';

    public function handle(SqsClient $sqs, NotificationEventProcessor $processor): int
    {
        $queueUrl = config('services.notifications.queue_url');

        do {
            $result = $sqs->receiveMessage([
                'QueueUrl' => $queueUrl,
                'MaxNumberOfMessages' => 10,
                'WaitTimeSeconds' => $this->option('once') ? 1 : 10,
            ]);

            foreach ($result['Messages'] ?? [] as $message) {
                $this->processMessage($sqs, $queueUrl, $message, $processor);
            }
        } while (! $this->option('once'));

        return self::SUCCESS;
    }

    /**
     * @param array<string, mixed> $message
     */
    private function processMessage(SqsClient $sqs, string $queueUrl, array $message, NotificationEventProcessor $processor): void
    {
        try {
            $event = json_decode($message['Body'], true, flags: JSON_THROW_ON_ERROR);
            $processor->process($event);

            $sqs->deleteMessage([
                'QueueUrl' => $queueUrl,
                'ReceiptHandle' => $message['ReceiptHandle'],
            ]);

            $this->info("Notified: {$event['detail-type']} ({$message['MessageId']})");
        } catch (Throwable $e) {
            $this->error("Could not process message {$message['MessageId']}: {$e->getMessage()}");
        }
    }
}
