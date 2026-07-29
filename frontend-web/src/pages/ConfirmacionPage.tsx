import { Link, Navigate, useLocation } from 'react-router-dom';
import type { Transferencia } from '../api/types';

interface LocationState {
  resultado?: Transferencia;
}

export function ConfirmacionPage() {
  const location = useLocation();
  const resultado = (location.state as LocationState | null)?.resultado;

  if (!resultado) {
    return <Navigate to="/transferencias" replace />;
  }

  return (
    <main>
      <h1>
        {resultado.estado === 'completada'
          ? 'Transferencia realizada'
          : 'Transferencia no realizada'}
      </h1>

      <dl>
        <dt>Estado</dt>
        <dd>{resultado.estado}</dd>

        <dt>Cuenta origen</dt>
        <dd>{resultado.cuenta_origen}</dd>

        <dt>Cuenta destino</dt>
        <dd>{resultado.cuenta_destino}</dd>

        <dt>Monto</dt>
        <dd>{resultado.monto.toFixed(2)}</dd>

        {resultado.motivo_falla && (
          <>
            <dt>Motivo</dt>
            <dd>{resultado.motivo_falla}</dd>
          </>
        )}
      </dl>

      <Link to="/">Volver a mis movimientos</Link>
    </main>
  );
}
