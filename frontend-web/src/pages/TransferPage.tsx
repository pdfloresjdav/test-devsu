import { useState, type FormEvent } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../auth/useAuth';
import { bffFetch, BffError } from '../api/bffClient';
import { getOrCreateIdempotencyKey, resetIdempotencyKey } from '../api/idempotency';
import type { Transfer } from '../api/types';

/**
 * Transfer screen (item 9.4). The Idempotency-Key is obtained ONCE per
 * attempt (getOrCreateIdempotencyKey) and is resent as-is if the user
 * retries after a timeout -- it's only discarded when the attempt ends
 * (success, or the user decides to cancel and start over).
 *
 * If the BFF/Transfers rejects with step_up_required (decision 3.6:
 * reinforced authentication for large amounts), the user is redirected to
 * re-authenticate instead of just showing an error -- keeping the SAME
 * Idempotency-Key so that, after re-authenticating, the retry doesn't
 * duplicate the operation. Detected via `error.code === 'step_up_required'`
 * (not by message text): bp-common now preserves the internal service's
 * business code instead of always flattening it to "upstream_error" (bug
 * found and fixed in Fase 10, see WORKLOG.md).
 */
export function TransferPage() {
  const { accessToken, login } = useAuth();
  const navigate = useNavigate();

  const [sourceAccount, setSourceAccount] = useState('');
  const [destinationAccount, setDestinationAccount] = useState('');
  const [amount, setAmount] = useState('');
  const [description, setDescription] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [sending, setSending] = useState(false);

  const send = async (event: FormEvent) => {
    event.preventDefault();
    setSending(true);
    setError(null);

    const idempotencyKey = getOrCreateIdempotencyKey();

    try {
      const result = await bffFetch<Transfer>('/transfers', {
        method: 'POST',
        accessToken: accessToken!,
        idempotencyKey,
        body: JSON.stringify({
          source_account: sourceAccount,
          destination_account: destinationAccount,
          amount: Number(amount),
          description,
        }),
      });

      resetIdempotencyKey();
      navigate('/transfers/confirmation', { state: { result } });
    } catch (err) {
      if (err instanceof BffError && err.code === 'step_up_required') {
        setError(
          'This transfer requires reinforced authentication. Please log in again to continue.',
        );
        await login();
        return;
      }

      setError(err instanceof BffError ? err.message : 'Could not process the transfer.');
    } finally {
      setSending(false);
    }
  };

  return (
    <main>
      <h1>New transfer</h1>

      <form onSubmit={send}>
        <label htmlFor="sourceAccount">Source account</label>
        <input
          id="sourceAccount"
          value={sourceAccount}
          onChange={(event) => setSourceAccount(event.target.value)}
          required
        />

        <label htmlFor="destinationAccount">Destination account</label>
        <input
          id="destinationAccount"
          value={destinationAccount}
          onChange={(event) => setDestinationAccount(event.target.value)}
          required
        />

        <label htmlFor="amount">Amount</label>
        <input
          id="amount"
          type="number"
          step="0.01"
          value={amount}
          onChange={(event) => setAmount(event.target.value)}
          required
        />

        <label htmlFor="description">Description</label>
        <input
          id="description"
          value={description}
          onChange={(event) => setDescription(event.target.value)}
        />

        <button type="submit" disabled={sending}>
          {sending ? 'Sending…' : 'Transfer'}
        </button>
      </form>

      {error && <p role="alert">{error}</p>}
    </main>
  );
}
