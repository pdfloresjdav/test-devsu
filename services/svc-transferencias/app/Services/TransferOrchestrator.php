<?php

namespace App\Services;

use App\Contracts\InterbankClient;
use App\Contracts\InterbankException;
use App\Contracts\SaldoInsuficienteException;
use App\Models\Cuenta;
use App\Models\Transferencia;
use BP\Common\Events\EventPublisherInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Orquesta la transferencia como una Saga (decision 3.4 / diagrama de
 * componentes 3b): debita en una transaccion local, intenta la
 * transferencia externa, y si falla ejecuta el paso de compensacion
 * (revierte el debito) en vez de dejar el sistema en un estado
 * inconsistente.
 */
class TransferOrchestrator
{
    public function __construct(
        private readonly InterbankClient $interbank,
        private readonly EventPublisherInterface $events,
    ) {
    }

    public function ejecutar(
        string $cuentaOrigen,
        string $cuentaDestino,
        float $monto,
        string $descripcion,
        string $idempotencyKey,
    ): Transferencia {
        $transferencia = $this->debitarYCrearTransferencia(
            $cuentaOrigen,
            $cuentaDestino,
            $monto,
            $descripcion,
            $idempotencyKey,
        );

        try {
            $this->interbank->ejecutar($cuentaDestino, $monto);
            $transferencia->update(['estado' => Transferencia::ESTADO_COMPLETADA]);

            $this->events->publish('TransferCompleted', $this->eventPayload($transferencia));
        } catch (InterbankException $e) {
            $this->compensar($cuentaOrigen, $monto);
            $transferencia->update([
                'estado' => Transferencia::ESTADO_FALLIDA,
                'motivo_falla' => $e->getMessage(),
            ]);

            $this->events->publish('TransferFailed', $this->eventPayload($transferencia));
        }

        return $transferencia->fresh();
    }

    private function debitarYCrearTransferencia(
        string $cuentaOrigen,
        string $cuentaDestino,
        float $monto,
        string $descripcion,
        string $idempotencyKey,
    ): Transferencia {
        return DB::transaction(function () use ($cuentaOrigen, $cuentaDestino, $monto, $descripcion, $idempotencyKey) {
            $cuenta = Cuenta::where('cuenta_id', $cuentaOrigen)->lockForUpdate()->firstOrFail();

            if (bccomp((string) $cuenta->saldo, (string) $monto, 2) < 0) {
                throw new SaldoInsuficienteException("La cuenta [{$cuentaOrigen}] no tiene saldo suficiente.");
            }

            $cuenta->decrement('saldo', $monto);

            return Transferencia::create([
                'transferencia_id' => (string) Str::uuid(),
                'idempotency_key' => $idempotencyKey,
                'cuenta_origen' => $cuentaOrigen,
                'cuenta_destino' => $cuentaDestino,
                'monto' => $monto,
                'descripcion' => $descripcion,
                'estado' => Transferencia::ESTADO_PENDIENTE,
            ]);
        });
    }

    private function compensar(string $cuentaOrigen, float $monto): void
    {
        DB::transaction(function () use ($cuentaOrigen, $monto) {
            Cuenta::where('cuenta_id', $cuentaOrigen)->lockForUpdate()->increment('saldo', $monto);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function eventPayload(Transferencia $transferencia): array
    {
        return [
            'transferencia_id' => $transferencia->transferencia_id,
            'cuenta_origen' => $transferencia->cuenta_origen,
            'cuenta_destino' => $transferencia->cuenta_destino,
            'monto' => (float) $transferencia->monto,
            'estado' => $transferencia->estado,
        ];
    }
}
