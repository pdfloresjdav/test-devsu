import { Navigate } from 'react-router-dom';
import { useAuth } from '../auth/useAuth';

export function LoginPage() {
  const { isAuthenticated, isLoading, login } = useAuth();

  if (isLoading) {
    return <p>Loading…</p>;
  }

  if (isAuthenticated) {
    return <Navigate to="/" replace />;
  }

  return (
    <main>
      <h1>BP Digital Banking</h1>
      <p>Log in to check your movements and make transfers.</p>
      <button onClick={login}>Log in</button>
    </main>
  );
}
