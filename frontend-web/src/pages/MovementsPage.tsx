import { useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../auth/useAuth';
import { bffFetch, BffError } from '../api/bffClient';
import type { Dashboard } from '../api/types';

const ACCOUNT_DEMO = import.meta.env.VITE_DEMO_ACCOUNT_ID ?? '1001';

/**
 * Movement history screen (item 9.3): uses the BFF's aggregate endpoint
 * (GET /dashboard/{accountId}), which composes customer and history data
 * in a single call.
 */
export function MovementsPage() {
  const { accessToken, logout } = useAuth();
  const [accountId, setAccountId] = useState(ACCOUNT_DEMO);
  const [dashboard, setDashboard] = useState<Dashboard | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const query = async (event: FormEvent) => {
    event.preventDefault();
    setLoading(true);
    setError(null);

    try {
      const data = await bffFetch<Dashboard>(`/dashboard/${accountId}`, {
        method: 'GET',
        accessToken: accessToken!,
      });
      setDashboard(data);
    } catch (err) {
      setDashboard(null);
      setError(err instanceof BffError ? err.message : 'Could not query the account.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <main>
      <header>
        <h1>My movements</h1>
        <button onClick={logout}>Log out</button>
      </header>

      <form onSubmit={query}>
        <label htmlFor="accountId">Account</label>
        <input
          id="accountId"
          value={accountId}
          onChange={(event) => setAccountId(event.target.value)}
        />
        <button type="submit" disabled={loading}>
          {loading ? 'Querying…' : 'Query'}
        </button>
      </form>

      {error && <p role="alert">{error}</p>}

      {dashboard && (
        <section>
          <h2>{dashboard.customer.name}</h2>
          <ul>
            {dashboard.recent_movements.map((movement) => (
              <li key={movement.movement_id}>
                {movement.date} — {movement.type} — {movement.amount.toFixed(2)} —{' '}
                {movement.description}
              </li>
            ))}
          </ul>
        </section>
      )}

      <Link to="/transfers">New transfer</Link>
    </main>
  );
}
