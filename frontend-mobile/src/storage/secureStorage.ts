import { Platform } from 'react-native';
import * as SecureStore from 'expo-secure-store';

/**
 * `expo-secure-store` (Keychain en iOS / Keystore en Android) no tiene
 * implementación en web. El fallback a `localStorage` en web es solo para
 * poder ejercitar el flujo completo con `expo start --web` en este entorno
 * (sin Xcode/Android Studio disponibles) — no ofrece la misma garantía de
 * seguridad que el almacenamiento nativo y no debe tomarse como la
 * verificación real de esta fase, solo como humo (smoke test).
 */
export async function setSecureItem(key: string, value: string): Promise<void> {
  if (Platform.OS === 'web') {
    window.localStorage.setItem(key, value);
    return;
  }

  await SecureStore.setItemAsync(key, value);
}

export async function getSecureItem(key: string): Promise<string | null> {
  if (Platform.OS === 'web') {
    return window.localStorage.getItem(key);
  }

  return SecureStore.getItemAsync(key);
}

export async function deleteSecureItem(key: string): Promise<void> {
  if (Platform.OS === 'web') {
    window.localStorage.removeItem(key);
    return;
  }

  await SecureStore.deleteItemAsync(key);
}
