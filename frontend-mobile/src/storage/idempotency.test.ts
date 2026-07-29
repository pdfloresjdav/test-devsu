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

  it('generates a new key when none is stored', async () => {
    const key = await getOrCreateIdempotencyKey();
    expect(key).toMatch(/^test-uuid-\d+$/);
  });

  it('reuses the same key across successive calls (retries of the same attempt)', async () => {
    const first = await getOrCreateIdempotencyKey();
    const second = await getOrCreateIdempotencyKey();
    expect(second).toBe(first);
  });

  it('generates a different key after resetting (new attempt)', async () => {
    const first = await getOrCreateIdempotencyKey();
    await resetIdempotencyKey();
    const second = await getOrCreateIdempotencyKey();
    expect(second).not.toBe(first);
  });
});
