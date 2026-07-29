const STORAGE_KEY = 'bp:transfer:idempotency-key';

/**
 * Un intento de transferencia usa la MISMA Idempotency-Key aunque el
 * usuario reintente por un timeout de red (decision 3.4/9.4) -- por eso
 * se persiste en sessionStorage en vez de generarse una nueva en cada
 * submit. Solo se limpia explicitamente cuando el intento termina
 * (exito o cuando el usuario decide empezar una transferencia nueva).
 */
export function getOrCreateIdempotencyKey(): string {
  let key = sessionStorage.getItem(STORAGE_KEY);

  if (!key) {
    key = crypto.randomUUID();
    sessionStorage.setItem(STORAGE_KEY, key);
  }

  return key;
}

export function resetIdempotencyKey(): void {
  sessionStorage.removeItem(STORAGE_KEY);
}
