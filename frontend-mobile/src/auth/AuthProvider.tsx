import { createContext, useEffect, useState, type ReactNode } from 'react';
import * as AuthSession from 'expo-auth-session';
import * as WebBrowser from 'expo-web-browser';
import { deleteSecureItem, getSecureItem, setSecureItem } from '../storage/secureStorage';
import { isBiometricAvailable, promptBiometric } from './biometric';
import { OIDC_CLIENT_ID, OIDC_ISSUER, OIDC_SCOPES, REFRESH_TOKEN_STORAGE_KEY } from './authConfig';

// Required by expo-auth-session on web to close the login popup when done.
WebBrowser.maybeCompleteAuthSession();

export interface AuthContextValue {
  isLoading: boolean;
  isAuthenticated: boolean;
  hasLinkedCredential: boolean;
  biometricAvailable: boolean;
  accessToken: string | null;
  request: AuthSession.AuthRequest | null;
  registerCredential: () => Promise<{ ok: boolean; error?: string }>;
  recurringLogin: () => Promise<{ ok: boolean; error?: string }>;
  logout: () => Promise<void>;
}

export const AuthContext = createContext<AuthContextValue | undefined>(undefined);

/**
 * Orchestrates items 10.3 (credential registration tied to biometrics) and
 * 10.4 (recurring login without going through the KYC provider again).
 *
 * Scope simplification documented in WORKLOG.md: mock-oidc doesn't
 * implement a real WebAuthn/FIDO2 ceremony (passkey registration against a
 * Relying Party) -- there's no way to test it against this project's local
 * infrastructure. Instead, "registering the credential" does the normal
 * Authorization Code + PKCE login (with the demo user already preloaded in
 * mock-oidc) and ties access to the resulting refresh_token behind a
 * native biometric gate (Face ID / BiometricPrompt via
 * expo-local-authentication) -- the same security property described in
 * architecture document decision 3.7 (the biometric data never leaves the
 * device, and recurring login doesn't repeat KYC verification), achieved
 * without needing a WebAuthn server that doesn't exist in this exercise.
 */
export function AuthProvider({ children }: { children: ReactNode }) {
  const discovery = AuthSession.useAutoDiscovery(OIDC_ISSUER);
  const [accessToken, setAccessToken] = useState<string | null>(null);
  const [hasLinkedCredential, setHasLinkedCredential] = useState(false);
  const [biometricAvailable, setBiometricAvailable] = useState(false);
  const [isLoading, setIsLoading] = useState(true);

  const redirectUri = AuthSession.makeRedirectUri({ path: 'callback' });

  const [request, , promptAsync] = AuthSession.useAuthRequest(
    {
      clientId: OIDC_CLIENT_ID,
      redirectUri,
      scopes: OIDC_SCOPES,
      usePKCE: true,
    },
    discovery,
  );

  useEffect(() => {
    Promise.all([getSecureItem(REFRESH_TOKEN_STORAGE_KEY), isBiometricAvailable()])
      .then(([storedRefreshToken, biometric]) => {
        setHasLinkedCredential(storedRefreshToken !== null);
        setBiometricAvailable(biometric);
      })
      .finally(() => setIsLoading(false));
  }, []);

  const registerCredential = async (): Promise<{ ok: boolean; error?: string }> => {
    if (!discovery) {
      return { ok: false, error: 'The OIDC issuer has not answered the discovery document yet.' };
    }

    const result = await promptAsync();

    if (result.type !== 'success') {
      return { ok: false, error: 'Login canceled or rejected.' };
    }

    const tokens = await AuthSession.exchangeCodeAsync(
      {
        clientId: OIDC_CLIENT_ID,
        code: result.params.code,
        redirectUri,
        extraParams: request?.codeVerifier ? { code_verifier: request.codeVerifier } : undefined,
      },
      discovery,
    );

    if (!tokens.refreshToken) {
      return {
        ok: false,
        error: 'The issuer did not return a refresh_token (check the offline_access scope).',
      };
    }

    const biometric = await isBiometricAvailable();
    setBiometricAvailable(biometric);

    if (biometric) {
      const confirmed = await promptBiometric('Confirm your identity to link access');

      if (!confirmed) {
        return { ok: false, error: 'Could not confirm biometrics. Please try again.' };
      }
    }

    await setSecureItem(REFRESH_TOKEN_STORAGE_KEY, tokens.refreshToken);
    setAccessToken(tokens.accessToken);
    setHasLinkedCredential(true);

    return { ok: true };
  };

  const recurringLogin = async (): Promise<{ ok: boolean; error?: string }> => {
    if (!discovery) {
      return { ok: false, error: 'The OIDC issuer has not answered the discovery document yet.' };
    }

    const storedRefreshToken = await getSecureItem(REFRESH_TOKEN_STORAGE_KEY);

    if (!storedRefreshToken) {
      return { ok: false, error: 'There is no credential linked on this device.' };
    }

    if (biometricAvailable) {
      const confirmed = await promptBiometric('Confirm your identity to continue');

      if (!confirmed) {
        return { ok: false, error: 'Biometrics not confirmed.' };
      }
    }

    const tokens = await AuthSession.refreshAsync(
      { clientId: OIDC_CLIENT_ID, refreshToken: storedRefreshToken },
      discovery,
    );

    if (tokens.refreshToken) {
      await setSecureItem(REFRESH_TOKEN_STORAGE_KEY, tokens.refreshToken);
    }

    setAccessToken(tokens.accessToken);
    return { ok: true };
  };

  const logout = async () => {
    await deleteSecureItem(REFRESH_TOKEN_STORAGE_KEY);
    setAccessToken(null);
    setHasLinkedCredential(false);
  };

  const value: AuthContextValue = {
    isLoading,
    isAuthenticated: accessToken !== null,
    hasLinkedCredential,
    biometricAvailable,
    accessToken,
    request,
    registerCredential,
    recurringLogin,
    logout,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}
