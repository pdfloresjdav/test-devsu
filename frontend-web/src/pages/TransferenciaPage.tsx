import { useState, type FormEvent } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../auth/useAuth';
import { bffFetch, BffError } from '../api/bffClient';
import { getOrCreateIdempotencyKey, resetIdempotencyKey } from '../api/idempotency';
import type { Transferencia } from '../api/types';

/**
 * Pantalla de transferencia (item 9.4). La Idempotency-Key se obtiene UNA
 * vez por intento (getOrCreateIdempotencyKey) y se reenvia igual si el
 * usuario reintenta por un timeout -- solo se descarta cuando el intento
 * termina (exito, o el usuario decide cancelar y empezar de nuevo).
 *
 * Si el BFF/Transferencias rechaza por step_up_required (decision 3.6:
 * autenticacion reforzada para montos grandes), se redirige a
 * reautenticarse en vez de solo mostrar un error -- conservando la MISMA
 * Idempotency-Key para que, tras reautenticarse, el reintento no duplique
 * la operacion.
 */
export function TransferenciaPage() {
  const { accessToken, login } = useAuth();
  const navigate = useNavigate();

  const [cuentaOrigen, setCuentaOrigen] = useState('');
  const [cuentaDestino, setCuentaDestino] = useState('');
  const [monto, setMonto] = useState('');
  const [descripcion, setDescripcion] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [enviando, setEnviando] = useState(false);

  const enviar = async (event: FormEvent) => {
    event.preventDefault();
    setEnviando(true);
    setError(null);

    const idempotencyKey = getOrCreateIdempotencyKey();

    try {
      const resultado = await bffFetch<Transferencia>('/transferencias', {
        method: 'POST',
        accessToken: accessToken!,
        idempotencyKey,
        body: JSON.stringify({
          cuenta_origen: cuentaOrigen,
          cuenta_destino: cuentaDestino,
          monto: Number(monto),
          descripcion,
        }),
      });

      resetIdempotencyKey();
      navigate('/transferencias/confirmacion', { state: { resultado } });
    } catch (err) {
      if (err instanceof BffError && err.code === 'upstream_error' && isStepUpError(err)) {
        setError(
          'Esta transferencia requiere autenticación reforzada. Volvé a iniciar sesión para continuar.',
        );
        await login();
        return;
      }

      setError(err instanceof BffError ? err.message : 'No se pudo procesar la transferencia.');
    } finally {
      setEnviando(false);
    }
  };

  return (
    <main>
      <h1>Nueva transferencia</h1>

      <form onSubmit={enviar}>
        <label htmlFor="cuentaOrigen">Cuenta origen</label>
        <input
          id="cuentaOrigen"
          value={cuentaOrigen}
          onChange={(event) => setCuentaOrigen(event.target.value)}
          required
        />

        <label htmlFor="cuentaDestino">Cuenta destino</label>
        <input
          id="cuentaDestino"
          value={cuentaDestino}
          onChange={(event) => setCuentaDestino(event.target.value)}
          required
        />

        <label htmlFor="monto">Monto</label>
        <input
          id="monto"
          type="number"
          step="0.01"
          value={monto}
          onChange={(event) => setMonto(event.target.value)}
          required
        />

        <label htmlFor="descripcion">Descripción</label>
        <input
          id="descripcion"
          value={descripcion}
          onChange={(event) => setDescripcion(event.target.value)}
        />

        <button type="submit" disabled={enviando}>
          {enviando ? 'Enviando…' : 'Transferir'}
        </button>
      </form>

      {error && <p role="alert">{error}</p>}
    </main>
  );
}

function isStepUpError(error: BffError): boolean {
  return (
    error.message.toLowerCase().includes('autenticacion reforzada') ||
    error.message.toLowerCase().includes('autenticación reforzada')
  );
}
