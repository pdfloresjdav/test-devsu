import { Platform } from 'react-native';
import * as SecureStore from 'expo-secure-store';

/**
 * `expo-secure-store` (Keychain on iOS / Keystore on Android) has no
 * implementation on web. The `localStorage` fallback on web is only to be
 * able to exercise the full flow with `expo start --web` in this
 * environment (without Xcode/Android Studio available) -- it doesn't offer
 * the same security guarantee as native storage and shouldn't be taken as
 * this phase's real verification, only as a smoke test.
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
