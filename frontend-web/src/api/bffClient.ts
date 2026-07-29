export class BffError extends Error {
  readonly status: number;
  readonly code: string;

  constructor(status: number, code: string, message: string) {
    super(message);
    this.name = 'BffError';
    this.status = status;
    this.code = code;
  }
}

interface BffRequestOptions extends RequestInit {
  accessToken: string;
  idempotencyKey?: string;
}

/**
 * Client toward the BFF Web. Never stores the token: it receives it
 * explicitly on each call (comes from AuthProvider) to make it clear that
 * it's the same user JWT being forwarded -- the BFF, in turn, forwards it
 * as-is to the internal microservices (decision 3.5).
 */
export async function bffFetch<T>(path: string, options: BffRequestOptions): Promise<T> {
  const { accessToken, idempotencyKey, headers, ...rest } = options;

  const response = await fetch(`${import.meta.env.VITE_BFF_WEB_URL}${path}`, {
    ...rest,
    headers: {
      'Content-Type': 'application/json',
      Authorization: `Bearer ${accessToken}`,
      ...(idempotencyKey ? { 'Idempotency-Key': idempotencyKey } : {}),
      ...headers,
    },
  });

  const body = await response.json().catch(() => null);

  if (!response.ok) {
    const code = body?.error?.code ?? 'unknown_error';
    const message = body?.error?.message ?? `Error ${response.status}`;
    throw new BffError(response.status, code, message);
  }

  return body?.data as T;
}
