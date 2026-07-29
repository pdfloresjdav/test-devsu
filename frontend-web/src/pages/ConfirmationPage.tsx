import { Link, Navigate, useLocation } from 'react-router-dom';
import type { Transfer } from '../api/types';

interface LocationState {
  result?: Transfer;
}

export function ConfirmationPage() {
  const location = useLocation();
  const result = (location.state as LocationState | null)?.result;

  if (!result) {
    return <Navigate to="/transfers" replace />;
  }

  return (
    <main>
      <h1>{result.status === 'completed' ? 'Transfer completed' : 'Transfer not completed'}</h1>

      <dl>
        <dt>Status</dt>
        <dd>{result.status}</dd>

        <dt>Source account</dt>
        <dd>{result.source_account}</dd>

        <dt>Destination account</dt>
        <dd>{result.destination_account}</dd>

        <dt>Amount</dt>
        <dd>{result.amount.toFixed(2)}</dd>

        {result.failure_reason && (
          <>
            <dt>Reason</dt>
            <dd>{result.failure_reason}</dd>
          </>
        )}
      </dl>

      <Link to="/">Back to my movements</Link>
    </main>
  );
}
