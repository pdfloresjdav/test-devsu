const STORAGE_KEY = 'bp:transfer:idempotency-key';

/**
 * A transfer attempt uses the SAME Idempotency-Key even if the user
 * retries after a network timeout (decision 3.4/9.4) -- that's why it's
 * persisted in sessionStorage instead of generating a new one on every
 * submit. It's only cleared explicitly when the attempt ends (success, or
 * when the user decides to start a new transfer).
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
