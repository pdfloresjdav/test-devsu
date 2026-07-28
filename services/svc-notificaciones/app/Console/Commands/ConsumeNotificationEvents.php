<?php

namespace App\Console\Commands;

use App\Services\NotificationEventProcessor;
use Aws\Sqs\SqsClient;
use Illuminate\Console\Command;
use Throwable;

/**
 * Consumidor de la cola SQS de Notificaciones (misma cola de eventos de
 * dominio que Auditoria, pero con su PROPIA cola/regla de EventBridge --
 * patron Pub/Sub con Competing Consumers, decision 3.13: cada consumidor
 * tiene su copia independiente de cada evento). Ver la nota sobre
 * Horizon/worker puro en CLAUDE.md y en svc-auditoria.
 */
class ConsumeNotificationEvents extends Command
{
    protected $signature = 'notifications:consume {--once : Procesa un unico ciclo de recepcion y termina (para tests/depuracion)}';

    protected $description = 'Consume eventos de dominio desde SQS y despacha las notificaciones correspondientes';

    public function handle(SqsClient $sqs, NotificationEventProcessor $processor): int
    {
        $queueUrl = config('services.notificaciones.queue_url');

        do {
            $result = $sqs->receiveMessage([
                'QueueUrl' => $queueUrl,
                'MaxNumberOfMessages' => 10,
                'WaitTimeSeconds' => $this->option('once') ? 1 : 10,
            ]);

            foreach ($result['Messages'] ?? [] as $message) {
                $this->procesarMensaje($sqs, $queueUrl, $message, $processor);
            }
        } while (! $this->option('once'));

        return self::SUCCESS;
    }

    /**
     * @param array<string, mixed> $message
     */
    private function procesarMensaje(SqsClient $sqs, string $queueUrl, array $message, NotificationEventProcessor $processor): void
    {
        try {
            $evento = json_decode($message['Body'], true, flags: JSON_THROW_ON_ERROR);
            $processor->procesar($evento);

            $sqs->deleteMessage([
                'QueueUrl' => $queueUrl,
                'ReceiptHandle' => $message['ReceiptHandle'],
            ]);

            $this->info("Notificado: {$evento['detail-type']} ({$message['MessageId']})");
        } catch (Throwable $e) {
            $this->error("No se pudo procesar el mensaje {$message['MessageId']}: {$e->getMessage()}");
        }
    }
}
