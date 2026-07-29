import AsyncStorage from '@react-native-async-storage/async-storage';
import * as Crypto from 'expo-crypto';

const STORAGE_KEY = 'bp:transfer:idempotency-key';

/**
 * Mismo criterio que en frontend-web (ver getOrCreateIdempotencyKey de la
 * SPA): un intento de transferencia reutiliza la MISMA Idempotency-Key
 * aunque se reintente por un timeout de red, para que el reintento no
 * duplique la operación. Se persiste con AsyncStorage (no es un dato
 * sensible, a diferencia del refresh token) para que sobreviva incluso si
 * la app pasa a segundo plano durante el reintento.
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
