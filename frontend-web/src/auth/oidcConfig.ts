import { UserManager, WebStorageStateStore, type UserManagerSettings } from 'oidc-client-ts';

/**
 * Authorization Code + PKCE (decision 3.6 del documento de arquitectura):
 * oidc-client-ts genera el code_verifier/code_challenge automaticamente
 * para response_type "code" -- no se reimplementa la logica de OAuth a
 * mano. OAUTH_MODE=local (mock-oidc) y auth0 usan el mismo cliente, solo
 * cambia VITE_OIDC_ISSUER/VITE_OIDC_CLIENT_ID.
 */
export const oidcSettings: UserManagerSettings = {
  authority: import.meta.env.VITE_OIDC_ISSUER,
  client_id: import.meta.env.VITE_OIDC_CLIENT_ID,
  redirect_uri: `${window.location.origin}/callback`,
  post_logout_redirect_uri: window.location.origin,
  response_type: 'code',
  scope: 'openid profile email offline_access',
  automaticSilentRenew: true,
  userStore: new WebStorageStateStore({ store: window.sessionStorage }),
};

export const userManager = new UserManager(oidcSettings);
