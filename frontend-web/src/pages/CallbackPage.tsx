import { useEffect, useState } from 'react';
import { Navigate } from 'react-router-dom';
import { userManager } from '../auth/oidcConfig';

/**
 * Receives the redirect back from the OIDC issuer with the authorization
 * code, and exchanges code + code_verifier (PKCE) for the tokens --
 * oidc-client-ts does this exchange, it's not reimplemented by hand.
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
    return <p>Completing login…</p>;
  }

  if (status === 'error') {
    return <p>Could not complete login. Please try again.</p>;
  }

  return <Navigate to="/" replace />;
}
