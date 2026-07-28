<?php

namespace App\Console\Commands;

use App\Services\AuditEventProcessor;
use Aws\Sqs\SqsClient;
use Illuminate\Console\Command;
use Throwable;

/**
 * Consumidor de la cola SQS que recibe los eventos de dominio ruteados por
 * EventBridge (patron Pub/Sub, decision 3.13). Este comando ES el "Laravel
 * Horizon Worker" del diagrama de contenedores -- en produccion corre como
 * proceso de larga duracion dentro de la tarea de ECS Fargate; Horizon en
 * si mismo supervisa colas internas de Laravel (Redis/database), que no es
 * la fuente de estos mensajes (vienen de SQS con el sobre de EventBridge),
 * asi que el long-polling se hace directo contra el SDK de AWS.
 */
class ConsumeAuditEvents extends Command
{
    protected $signature = 'audit:consume {--once : Procesa un unico ciclo de recepcion y termina (para tests/depuracion)}';

    protected $description = 'Consume eventos de dominio desde SQS y los persiste como registros de auditoria';

    public function handle(SqsClient $sqs, AuditEventProcessor $processor): int
    {
        $queueUrl = config('services.auditoria.queue_url');

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
    private function procesarMensaje(SqsClient $sqs, string $queueUrl, array $message, AuditEventProcessor $processor): void
    {
        try {
            $evento = json_decode($message['Body'], true, flags: JSON_THROW_ON_ERROR);
            $processor->procesar($evento);

            $sqs->deleteMessage([
                'QueueUrl' => $queueUrl,
                'ReceiptHandle' => $message['ReceiptHandle'],
            ]);

            $this->info("Auditado: {$evento['detail-type']} ({$message['MessageId']})");
        } catch (Throwable $e) {
            // No se borra el mensaje: al vencer la visibilidad vuelve a
            // aparecer en la cola: y tras varios intentos fallidos, SQS lo
            // manda solo a la DLQ (redrive policy configurada en
            // audit:setup-queue). No queremos perder el evento.
            $this->error("No se pudo procesar el mensaje {$message['MessageId']}: {$e->getMessage()}");
        }
    }
}
