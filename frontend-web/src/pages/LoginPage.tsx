import { Navigate } from 'react-router-dom';
import { useAuth } from '../auth/useAuth';

export function LoginPage() {
  const { isAuthenticated, isLoading, login } = useAuth();

  if (isLoading) {
    return <p>Cargando…</p>;
  }

  if (isAuthenticated) {
    return <Navigate to="/" replace />;
  }

  return (
    <main>
      <h1>Banca Digital BP</h1>
      <p>Iniciá sesión para consultar tus movimientos y hacer transferencias.</p>
      <button onClick={login}>Iniciar sesión</button>
    </main>
  );
}
