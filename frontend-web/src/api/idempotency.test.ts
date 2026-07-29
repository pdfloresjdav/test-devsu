import { describe, it, expect, beforeEach } from 'vitest';
import { getOrCreateIdempotencyKey, resetIdempotencyKey } from './idempotency';

describe('idempotency', () => {
  beforeEach(() => {
    sessionStorage.clear();
  });

  it('generates a new key when none is stored', () => {
    const key = getOrCreateIdempotencyKey();
    expect(key).toMatch(/^[0-9a-f-]{36}$/);
  });

  it('reuses the same key across successive calls (retries of the same attempt)', () => {
    const first = getOrCreateIdempotencyKey();
    const second = getOrCreateIdempotencyKey();
    expect(second).toBe(first);
  });

  it('generates a different key after resetting (new attempt)', () => {
    const first = getOrCreateIdempotencyKey();
    resetIdempotencyKey();
    const second = getOrCreateIdempotencyKey();
    expect(second).not.toBe(first);
  });
});
