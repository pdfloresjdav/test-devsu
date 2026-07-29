import { describe, it, expect, beforeEach } from 'vitest';
import { getOrCreateIdempotencyKey, resetIdempotencyKey } from './idempotency';

describe('idempotency', () => {
  beforeEach(() => {
    sessionStorage.clear();
  });

  it('genera una clave nueva cuando no hay ninguna guardada', () => {
    const key = getOrCreateIdempotencyKey();
    expect(key).toMatch(/^[0-9a-f-]{36}$/);
  });

  it('reutiliza la misma clave en llamadas sucesivas (reintentos del mismo intento)', () => {
    const first = getOrCreateIdempotencyKey();
    const second = getOrCreateIdempotencyKey();
    expect(second).toBe(first);
  });

  it('genera una clave distinta luego de reiniciar (nuevo intento)', () => {
    const first = getOrCreateIdempotencyKey();
    resetIdempotencyKey();
    const second = getOrCreateIdempotencyKey();
    expect(second).not.toBe(first);
  });
});
