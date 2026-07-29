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
 * Cliente hacia el BFF Web. Nunca guarda el token: lo recibe explicito en
 * cada llamada (viene del AuthProvider) para que quede claro que es el
 * mismo JWT del usuario el que se reenvia -- el BFF, a su vez, lo reenvia
 * tal cual a los microservicios internos (decision 3.5).
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
