export const OIDC_ISSUER = process.env.EXPO_PUBLIC_OIDC_ISSUER ?? 'http://localhost:4011';

/**
 * Same client_id "bp-web" as the SPA: it's the only public client
 * preconfigured in mock-oidc's CLIENTS_CONFIGURATION_INLINE
 * (docker-compose.yml), with redirect_uri http://localhost:19006/callback
 * already reserved for this app (see CLAUDE.md).
 */
export const OIDC_CLIENT_ID = process.env.EXPO_PUBLIC_OIDC_CLIENT_ID ?? 'bp-web';

// "bp-web" is the API scope/resource configured in mock-oidc (and the one
// that would need to be declared the same way in real Auth0/Okta) so the
// access token carries an "aud" -- without requesting it explicitly the
// token has no audience at all and bp-common rejects it (see
// docker-compose.yml).
export const OIDC_SCOPES = ['openid', 'profile', 'email', 'offline_access', 'bp-web'];

export const REFRESH_TOKEN_STORAGE_KEY = 'bp:mobile:refresh-token';
