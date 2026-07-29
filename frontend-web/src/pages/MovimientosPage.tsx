import { useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../auth/useAuth';
import { bffFetch, BffError } from '../api/bffClient';
import type { Dashboard } from '../api/types';

const CUENTA_DEMO = import.meta.env.VITE_DEMO_CUENTA_ID ?? '1001';

/**
 * Pantalla de histórico de movimientos (item 9.3): usa el endpoint
 * agregado del BFF (GET /dashboard/{cuentaId}), que compone datos del
 * cliente y del histórico en un solo llamado.
 */
export function MovimientosPage() {
  const { accessToken, logout } = useAuth();
  const [cuentaId, setCuentaId] = useState(CUENTA_DEMO);
  const [dashboard, setDashboard] = useState<Dashboard | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const consultar = async (event: FormEvent) => {
    event.preventDefault();
    setLoading(true);
    setError(null);

    try {
      const data = await bffFetch<Dashboard>(`/dashboard/${cuentaId}`, {
        method: 'GET',
        accessToken: accessToken!,
      });
      setDashboard(data);
    } catch (err) {
      setDashboard(null);
      setError(err instanceof BffError ? err.message : 'No se pudo consultar la cuenta.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <main>
      <header>
        <h1>Mis movimientos</h1>
        <button onClick={logout}>Cerrar sesión</button>
      </header>

      <form onSubmit={consultar}>
        <label htmlFor="cuentaId">Cuenta</label>
        <input
          id="cuentaId"
          value={cuentaId}
          onChange={(event) => setCuentaId(event.target.value)}
        />
        <button type="submit" disabled={loading}>
          {loading ? 'Consultando…' : 'Consultar'}
        </button>
      </form>

      {error && <p role="alert">{error}</p>}

      {dashboard && (
        <section>
          <h2>{dashboard.cliente.nombre}</h2>
          <ul>
            {dashboard.movimientos_recientes.map((movimiento) => (
              <li key={movimiento.movimiento_id}>
                {movimiento.fecha} — {movimiento.tipo} — {movimiento.monto.toFixed(2)} —{' '}
                {movimiento.descripcion}
              </li>
            ))}
          </ul>
        </section>
      )}

      <Link to="/transferencias">Nueva transferencia</Link>
    </main>
  );
}
