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
  accessToken?: string;
  idempotencyKey?: string;
}

const BFF_MOBILE_URL = process.env.EXPO_PUBLIC_BFF_MOBILE_URL ?? 'http://localhost:8004';

/**
 * Cliente hacia el BFF Móvil. Mismo envelope {data}/{error:{code,message}}
 * que expone BP\Common\Http\ApiResponse (igual convención que bffFetch en
 * frontend-web) — `accessToken` es opcional porque el onboarding no
 * requiere JWT (el cliente todavía no existe en el IdP).
 */
export async function bffFetch<T>(path: string, options: BffRequestOptions = {}): Promise<T> {
  const { accessToken, idempotencyKey, headers, ...rest } = options;

  const response = await fetch(`${BFF_MOBILE_URL}${path}`, {
    ...rest,
    headers: {
      'Content-Type': 'application/json',
      ...(accessToken ? { Authorization: `Bearer ${accessToken}` } : {}),
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
