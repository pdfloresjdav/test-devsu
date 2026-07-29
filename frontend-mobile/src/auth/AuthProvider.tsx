import { createContext, useEffect, useState, type ReactNode } from 'react';
import * as AuthSession from 'expo-auth-session';
import * as WebBrowser from 'expo-web-browser';
import { deleteSecureItem, getSecureItem, setSecureItem } from '../storage/secureStorage';
import { isBiometricAvailable, promptBiometric } from './biometric';
import { OIDC_CLIENT_ID, OIDC_ISSUER, OIDC_SCOPES, REFRESH_TOKEN_STORAGE_KEY } from './authConfig';

// Requerido por expo-auth-session en web para cerrar el popup de login al terminar.
WebBrowser.maybeCompleteAuthSession();

export interface AuthContextValue {
  isLoading: boolean;
  isAuthenticated: boolean;
  hasLinkedCredential: boolean;
  biometricAvailable: boolean;
  accessToken: string | null;
  request: AuthSession.AuthRequest | null;
  registrarCredencial: () => Promise<{ ok: boolean; error?: string }>;
  loginRecurrente: () => Promise<{ ok: boolean; error?: string }>;
  logout: () => Promise<void>;
}

export const AuthContext = createContext<AuthContextValue | undefined>(undefined);

/**
 * Orquesta los items 10.3 (registro de credencial atada a biometría) y
 * 10.4 (login recurrente sin volver a pasar por el proveedor KYC).
 *
 * Simplificación de alcance documentada en WORKLOG.md: mock-oidc no
 * implementa un ceremonial WebAuthn/FIDO2 real (registro de passkey contra
 * un Relying Party) — no hay forma de probarlo contra la infraestructura
 * local de este proyecto. En su lugar, "registrar la credencial" hace el
 * login Authorization Code + PKCE normal (con el usuario demo ya
 * precargado en mock-oidc) y ata el acceso al refresh_token resultante
 * detrás de un gate de biometría nativa (Face ID / BiometricPrompt vía
 * expo-local-authentication) — la misma propiedad de seguridad que
 * describe la decisión 3.7 del documento de arquitectura (el dato
 * biométrico nunca sale del dispositivo, y el login recurrente no repite
 * la verificación KYC), lograda sin necesitar un servidor WebAuthn que no
 * existe en este ejercicio.
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

  const registrarCredencial = async (): Promise<{ ok: boolean; error?: string }> => {
    if (!discovery) {
      return { ok: false, error: 'El emisor OIDC todavía no respondió el discovery document.' };
    }

    const result = await promptAsync();

    if (result.type !== 'success') {
      return { ok: false, error: 'Inicio de sesión cancelado o rechazado.' };
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
        error: 'El emisor no devolvió un refresh_token (revisar scope offline_access).',
      };
    }

    const biometric = await isBiometricAvailable();
    setBiometricAvailable(biometric);

    if (biometric) {
      const confirmed = await promptBiometric('Confirmá tu identidad para vincular el acceso');

      if (!confirmed) {
        return { ok: false, error: 'No se pudo confirmar la biometría. Probá de nuevo.' };
      }
    }

    await setSecureItem(REFRESH_TOKEN_STORAGE_KEY, tokens.refreshToken);
    setAccessToken(tokens.accessToken);
    setHasLinkedCredential(true);

    return { ok: true };
  };

  const loginRecurrente = async (): Promise<{ ok: boolean; error?: string }> => {
    if (!discovery) {
      return { ok: false, error: 'El emisor OIDC todavía no respondió el discovery document.' };
    }

    const storedRefreshToken = await getSecureItem(REFRESH_TOKEN_STORAGE_KEY);

    if (!storedRefreshToken) {
      return { ok: false, error: 'No hay una credencial vinculada en este dispositivo.' };
    }

    if (biometricAvailable) {
      const confirmed = await promptBiometric('Confirmá tu identidad para continuar');

      if (!confirmed) {
        return { ok: false, error: 'Biometría no confirmada.' };
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
    registrarCredencial,
    loginRecurrente,
    logout,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}
