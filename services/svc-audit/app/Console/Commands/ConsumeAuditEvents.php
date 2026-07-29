<?php

namespace App\Console\Commands;

use App\Services\AuditEventProcessor;
use Aws\Sqs\SqsClient;
use Illuminate\Console\Command;
use Throwable;

/**
 * Consumer of the SQS queue that receives domain events routed by
 * EventBridge (Pub/Sub pattern, decision 3.13). This command IS the
 * "Laravel Horizon Worker" from the container diagram -- in production it
 * runs as a long-lived process inside the ECS Fargate task; Horizon
 * itself supervises Laravel's internal queues (Redis/database), which
 * isn't the source of these messages (they come from SQS with the
 * EventBridge envelope), so the long-polling is done directly against the
 * AWS SDK.
 */
class ConsumeAuditEvents extends Command
{
    protected $signature = 'audit:consume {--once : Processes a single receive cycle and exits (for tests/debugging)}';

    protected $description = 'Consumes domain events from SQS and persists them as audit records';

    public function handle(SqsClient $sqs, AuditEventProcessor $processor): int
    {
        $queueUrl = config('services.audit.queue_url');

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
     * @param  array<string, mixed>  $message
     */
    private function processMessage(SqsClient $sqs, string $queueUrl, array $message, AuditEventProcessor $processor): void
    {
        try {
            $event = json_decode($message['Body'], true, flags: JSON_THROW_ON_ERROR);
            $processor->process($event);

            $sqs->deleteMessage([
                'QueueUrl' => $queueUrl,
                'ReceiptHandle' => $message['ReceiptHandle'],
            ]);

            $this->info("Audited: {$event['detail-type']} ({$message['MessageId']})");
        } catch (Throwable $e) {
            // The message isn't deleted: once its visibility expires it
            // reappears on the queue, and after several failed attempts
            // SQS moves it to the DLQ on its own (redrive policy
            // configured in audit:setup-infrastructure). We don't want to
            // lose the event.
            $this->error("Could not process message {$message['MessageId']}: {$e->getMessage()}");
        }
    }
}
