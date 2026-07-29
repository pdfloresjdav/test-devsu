import AsyncStorage from '@react-native-async-storage/async-storage';
import * as Crypto from 'expo-crypto';

const STORAGE_KEY = 'bp:transfer:idempotency-key';

/**
 * Same criterion as in frontend-web (see the SPA's
 * getOrCreateIdempotencyKey): a transfer attempt reuses the SAME
 * Idempotency-Key even if it's retried after a network timeout, so the
 * retry doesn't duplicate the operation. Persisted with AsyncStorage (not
 * sensitive data, unlike the refresh token) so it survives even if the app
 * goes to the background during the retry.
 */
export async function getOrCreateIdempotencyKey(): Promise<string> {
  const existing = await AsyncStorage.getItem(STORAGE_KEY);

  if (existing) {
    return existing;
  }

  const key = Crypto.randomUUID();
  await AsyncStorage.setItem(STORAGE_KEY, key);
  return key;
}

export async function resetIdempotencyKey(): Promise<void> {
  await AsyncStorage.removeItem(STORAGE_KEY);
}
