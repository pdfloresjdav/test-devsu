export const OIDC_ISSUER = process.env.EXPO_PUBLIC_OIDC_ISSUER ?? 'http://localhost:4011';

/**
 * Mismo client_id "bp-web" que la SPA: es el único cliente público
 * preconfigurado en CLIENTS_CONFIGURATION_INLINE de mock-oidc
 * (docker-compose.yml), con redirect_uri http://localhost:19006/callback
 * ya reservado para esta app (ver CLAUDE.md).
 */
export const OIDC_CLIENT_ID = process.env.EXPO_PUBLIC_OIDC_CLIENT_ID ?? 'bp-web';

export const OIDC_SCOPES = ['openid', 'profile', 'email', 'offline_access'];

export const REFRESH_TOKEN_STORAGE_KEY = 'bp:mobile:refresh-token';
