<?php

namespace App\Services;

use App\Contracts\InsufficientBalanceException;
use App\Contracts\InterbankClient;
use App\Contracts\InterbankException;
use App\Models\Account;
use App\Models\Transfer;
use BP\Common\Events\EventPublisherInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Orchestrates the transfer as a Saga (decision 3.4 / component diagram
 * 3b): debits within a local transaction, attempts the external transfer,
 * and if it fails runs the compensation step (reverts the debit) instead
 * of leaving the system in an inconsistent state.
 */
class TransferOrchestrator
{
    public function __construct(
        private readonly InterbankClient $interbank,
        private readonly EventPublisherInterface $events,
    ) {}

    public function execute(
        string $sourceAccount,
        string $destinationAccount,
        float $amount,
        string $description,
        string $idempotencyKey,
        string $actor = 'system',
    ): Transfer {
        $transfer = $this->debitAndCreateTransfer(
            $sourceAccount,
            $destinationAccount,
            $amount,
            $description,
            $idempotencyKey,
        );

        try {
            $this->interbank->execute($destinationAccount, $amount);
            $transfer->update(['status' => Transfer::STATUS_COMPLETED]);

            $this->events->publish('TransferCompleted', $this->eventPayload($transfer, $actor));
        } catch (InterbankException $e) {
            $this->compensate($sourceAccount, $amount);
            $transfer->update([
                'status' => Transfer::STATUS_FAILED,
                'failure_reason' => $e->getMessage(),
            ]);

            $this->events->publish('TransferFailed', $this->eventPayload($transfer, $actor));
        }

        return $transfer->fresh();
    }

    private function debitAndCreateTransfer(
        string $sourceAccount,
        string $destinationAccount,
        float $amount,
        string $description,
        string $idempotencyKey,
    ): Transfer {
        return DB::transaction(function () use ($sourceAccount, $destinationAccount, $amount, $description, $idempotencyKey) {
            $account = Account::where('account_id', $sourceAccount)->lockForUpdate()->firstOrFail();

            if (bccomp((string) $account->balance, (string) $amount, 2) < 0) {
                throw new InsufficientBalanceException("Account [{$sourceAccount}] doesn't have sufficient balance.");
            }

            $account->decrement('balance', $amount);

            return Transfer::create([
                'transfer_id' => (string) Str::uuid(),
                'idempotency_key' => $idempotencyKey,
                'source_account' => $sourceAccount,
                'destination_account' => $destinationAccount,
                'amount' => $amount,
                'description' => $description,
                'status' => Transfer::STATUS_PENDING,
            ]);
        });
    }

    private function compensate(string $sourceAccount, float $amount): void
    {
        DB::transaction(function () use ($sourceAccount, $amount) {
            Account::where('account_id', $sourceAccount)->lockForUpdate()->increment('balance', $amount);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function eventPayload(Transfer $transfer, string $actor): array
    {
        return [
            'transfer_id' => $transfer->transfer_id,
            'source_account' => $transfer->source_account,
            'destination_account' => $transfer->destination_account,
            'amount' => (float) $transfer->amount,
            'status' => $transfer->status,
            'actor' => $actor,
        ];
    }
}
