import { describe, it, expect, beforeEach, jest } from '@jest/globals';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { getOrCreateIdempotencyKey, resetIdempotencyKey } from './idempotency';

jest.mock('@react-native-async-storage/async-storage', () =>
  jest.requireActual('@react-native-async-storage/async-storage/jest/async-storage-mock'),
);

let mockUuidCounter = 0;
jest.mock('expo-crypto', () => ({
  randomUUID: jest.fn(() => `test-uuid-${mockUuidCounter++}`),
}));

describe('idempotency (frontend-mobile)', () => {
  beforeEach(async () => {
    await AsyncStorage.clear();
  });

  it('genera una clave nueva cuando no hay ninguna guardada', async () => {
    const key = await getOrCreateIdempotencyKey();
    expect(key).toMatch(/^test-uuid-\d+$/);
  });

  it('reutiliza la misma clave en llamadas sucesivas (reintentos del mismo intento)', async () => {
    const first = await getOrCreateIdempotencyKey();
    const second = await getOrCreateIdempotencyKey();
    expect(second).toBe(first);
  });

  it('genera una clave distinta luego de reiniciar (nuevo intento)', async () => {
    const first = await getOrCreateIdempotencyKey();
    await resetIdempotencyKey();
    const second = await getOrCreateIdempotencyKey();
    expect(second).not.toBe(first);
  });
});
