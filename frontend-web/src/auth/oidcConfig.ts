import { UserManager, WebStorageStateStore, type UserManagerSettings } from 'oidc-client-ts';

/**
 * Authorization Code + PKCE (architecture document decision 3.6):
 * oidc-client-ts generates the code_verifier/code_challenge automatically
 * for response_type "code" -- the OAuth logic is not reimplemented by
 * hand. OAUTH_MODE=local (mock-oidc) and auth0 use the same client, only
 * VITE_OIDC_ISSUER/VITE_OIDC_CLIENT_ID changes.
 */
export const oidcSettings: UserManagerSettings = {
  authority: import.meta.env.VITE_OIDC_ISSUER,
  client_id: import.meta.env.VITE_OIDC_CLIENT_ID,
  redirect_uri: `${window.location.origin}/callback`,
  post_logout_redirect_uri: window.location.origin,
  response_type: 'code',
  // "bp-web" is the API scope/resource configured in mock-oidc (and the
  // one that would need to be declared the same way in real Auth0/Okta)
  // so the access token carries an "aud" -- without requesting it
  // explicitly the token has no audience at all and bp-common rejects it
  // (see docker-compose.yml).
  scope: 'openid profile email offline_access bp-web',
  automaticSilentRenew: true,
  userStore: new WebStorageStateStore({ store: window.sessionStorage }),
};

export const userManager = new UserManager(oidcSettings);
