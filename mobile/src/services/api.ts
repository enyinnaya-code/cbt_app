/**
 * The app's only door to the server. It never throws raw network errors: callers get an ApiError with a
 * plain-English message and a kind, so screens can say "you are offline" instead of "Network request failed".
 */
export type ApiErrorKind = 'offline' | 'timeout' | 'unauthorized' | 'forbidden' | 'validation' | 'throttled' | 'server' | 'unknown';

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly kind: ApiErrorKind,
    public readonly status: number = 0,
    /** Field errors from a 422, keyed by field name. */
    public readonly fields: Record<string, string[]> = {},
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

export interface ApiOptions {
  baseUrl: string;
  getToken: () => string | null;
  /** Called on any 401 for a request that carried a token, so the app can ask the student to sign in again. */
  onUnauthorized?: () => void;
  timeoutMs?: number;
  fetchImpl?: typeof fetch;
  deviceName?: () => string;
}

export interface ApiResponse<T> {
  status: number;
  data: T;
  etag: string | null;
}

export interface RequestOptions {
  method?: 'GET' | 'POST' | 'PUT' | 'DELETE';
  body?: unknown;
  etag?: string | null;
  auth?: boolean;
  signal?: AbortSignal;
  timeoutMs?: number;
}

export class Api {
  constructor(private readonly opts: ApiOptions) {}

  get baseUrl(): string {
    return this.opts.baseUrl.replace(/\/+$/, '');
  }

  url(path: string): string {
    return path.startsWith('http') ? path : `${this.baseUrl}${path.startsWith('/') ? '' : '/'}${path}`;
  }

  headers(extra: Record<string, string> = {}, auth = true): Record<string, string> {
    const h: Record<string, string> = { Accept: 'application/json', ...extra };
    const token = auth ? this.opts.getToken() : null;
    if (token) h.Authorization = `Bearer ${token}`;
    return h;
  }

  async request<T = unknown>(path: string, o: RequestOptions = {}): Promise<ApiResponse<T>> {
    const f = this.opts.fetchImpl ?? fetch;
    const auth = o.auth ?? true;
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), o.timeoutMs ?? this.opts.timeoutMs ?? 20000);
    o.signal?.addEventListener('abort', () => controller.abort());

    const extra: Record<string, string> = {};
    if (o.body !== undefined) extra['Content-Type'] = 'application/json';
    if (o.etag) extra['If-None-Match'] = o.etag;

    let res: Response;
    try {
      res = await f(this.url(path), {
        method: o.method ?? (o.body !== undefined ? 'POST' : 'GET'),
        headers: this.headers(extra, auth),
        body: o.body !== undefined ? JSON.stringify(o.body) : undefined,
        signal: controller.signal,
      });
    } catch (e) {
      if (o.signal?.aborted) throw new ApiError('Cancelled.', 'unknown');
      const timedOut = controller.signal.aborted;
      throw new ApiError(timedOut ? 'The server took too long to answer.' : 'You are offline, or the server cannot be reached.', timedOut ? 'timeout' : 'offline');
    } finally {
      clearTimeout(timer);
    }

    if (res.status === 304) return { status: 304, data: undefined as T, etag: o.etag ?? null };

    let data: unknown = null;
    const text = await res.text();
    if (text) {
      try { data = JSON.parse(text); } catch { data = null; }
    }

    if (res.ok) return { status: res.status, data: data as T, etag: res.headers.get('ETag') };

    const message = (data as { message?: string } | null)?.message;

    if (res.status === 401) {
      if (auth && this.opts.getToken()) this.opts.onUnauthorized?.();
      throw new ApiError(message ?? 'Please sign in again.', 'unauthorized', 401);
    }
    if (res.status === 403) throw new ApiError(message ?? 'You are not allowed to do that.', 'forbidden', 403);
    if (res.status === 422) {
      const fields = ((data as { errors?: Record<string, string[]> } | null)?.errors) ?? {};
      const first = Object.values(fields)[0]?.[0];
      throw new ApiError(first ?? message ?? 'Please check what you typed.', 'validation', 422, fields);
    }
    if (res.status === 429) throw new ApiError(message ?? 'Too many attempts. Please wait a moment.', 'throttled', 429);
    if (res.status >= 500) throw new ApiError('Something went wrong on our side. Please try again.', 'server', res.status);
    throw new ApiError(message ?? `Unexpected response (${res.status}).`, 'unknown', res.status);
  }

  get<T>(path: string, o: Omit<RequestOptions, 'method' | 'body'> = {}) {
    return this.request<T>(path, { ...o, method: 'GET' });
  }

  post<T>(path: string, body: unknown = {}, o: Omit<RequestOptions, 'method' | 'body'> = {}) {
    return this.request<T>(path, { ...o, method: 'POST', body });
  }

  get deviceName(): string {
    return this.opts.deviceName?.() ?? 'Phone';
  }
}
