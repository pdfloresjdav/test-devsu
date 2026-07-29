import { useEffect, useState } from 'react';
import { Navigate } from 'react-router-dom';
import { userManager } from '../auth/oidcConfig';

/**
 * Recibe la redireccion de vuelta del emisor OIDC con el authorization
 * code, e intercambia code + code_verifier (PKCE) por los tokens --
 * oidc-client-ts hace este intercambio, no se reimplementa a mano.
 */
export function CallbackPage() {
  const [status, setStatus] = useState<'processing' | 'done' | 'error'>('processing');

  useEffect(() => {
    userManager
      .signinRedirectCallback()
      .then(() => setStatus('done'))
      .catch(() => setStatus('error'));
  }, []);

  if (status === 'processing') {
    return <p>Completando el inicio de sesión…</p>;
  }

  if (status === 'error') {
    return <p>No se pudo completar el inicio de sesión. Volvé a intentarlo.</p>;
  }

  return <Navigate to="/" replace />;
}
