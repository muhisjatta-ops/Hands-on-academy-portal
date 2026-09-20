const BASE = import.meta.env.VITE_API_URL ?? 'http://localhost:8000';

/**
 * Laravel returns 422 with {message, errors: {field: [msg, ...]}}.
 * Carrying that shape through to the UI is what lets a form highlight
 * the field that was wrong instead of showing one generic banner.
 */
export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
    public errors: Record<string, string[]> = {},
    public code?: string,
  ) {
    super(message);
    this.name = 'ApiError';
  }

  /** First error message for a field, if any. */
  field(name: string): string | undefined {
    return this.errors[name]?.[0];
  }
}

let csrfReady = false;

/**
 * Sanctum sets an XSRF-TOKEN cookie which we must echo back in a header.
 * Called once before the first mutating request, and again after a 419
 * (token mismatch — usually a session that outlived the token).
 */
async function ensureCsrfCookie(force = false): Promise<void> {
  if (csrfReady && !force) return;

  await fetch(`${BASE}/sanctum/csrf-cookie`, { credentials: 'include' });
  csrfReady = true;
}

function readCookie(name: string): string | null {
  const match = document.cookie.match(new RegExp(`(^|;\\s*)${name}=([^;]*)`));
  return match ? decodeURIComponent(match[2]) : null;
}

interface RequestOptions {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
  body?: unknown;
  /** Set false for endpoints that should not trigger a CSRF fetch. */
  csrf?: boolean;
}

async function request<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const { method = 'GET', body, csrf = method !== 'GET' } = options;

  if (csrf) await ensureCsrfCookie();

  const send = async (): Promise<Response> => {
    const headers: Record<string, string> = {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    };

    if (body !== undefined) headers['Content-Type'] = 'application/json';

    const token = readCookie('XSRF-TOKEN');
    if (token) headers['X-XSRF-TOKEN'] = token;

    return fetch(`${BASE}/api${path}`, {
      method,
      credentials: 'include',
      headers,
      body: body === undefined ? undefined : JSON.stringify(body),
    });
  };

  let response = await send();

  // 419 = CSRF token expired. Refresh once and retry, quietly.
  if (response.status === 419) {
    await ensureCsrfCookie(true);
    response = await send();
  }

  if (response.status === 204) return undefined as T;

  const payload = await response.json().catch(() => ({}));

  if (!response.ok) {
    throw new ApiError(
      response.status,
      payload.message ?? 'Something went wrong. Please try again.',
      payload.errors ?? {},
      payload.code,
    );
  }

  return payload as T;
}

export const api = {
  get: <T>(path: string) => request<T>(path),
  post: <T>(path: string, body?: unknown) => request<T>(path, { method: 'POST', body }),
  put: <T>(path: string, body?: unknown) => request<T>(path, { method: 'PUT', body }),
  patch: <T>(path: string, body?: unknown) => request<T>(path, { method: 'PATCH', body }),
  delete: <T>(path: string, body?: unknown) => request<T>(path, { method: 'DELETE', body }),
};
