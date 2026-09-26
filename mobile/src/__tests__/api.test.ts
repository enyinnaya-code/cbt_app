import { Api, ApiError } from '@/services/api';
import { res } from './helpers/fixtures';

const make = (fetchImpl: typeof fetch, token: string | null = 'tok', onUnauthorized = jest.fn()) =>
  ({ api: new Api({ baseUrl: 'https://x.test/api/v1/', getToken: () => token, onUnauthorized, fetchImpl, timeoutMs: 200 }), onUnauthorized });

const errorOf = async (p: Promise<unknown>): Promise<ApiError> => {
  try { await p; } catch (e) { return e as ApiError; }
  throw new Error('expected a rejection');
};

describe('Api', () => {
  it('builds URLs and sends the token and JSON headers', async () => {
    const f = jest.fn().mockResolvedValue(res(200, { ok: true }));
    const { api } = make(f);

    await api.post('/auth/login', { a: 1 });

    const [url, init] = f.mock.calls[0];
    expect(url).toBe('https://x.test/api/v1/auth/login');
    expect(init.method).toBe('POST');
    expect(init.headers).toMatchObject({ Accept: 'application/json', Authorization: 'Bearer tok', 'Content-Type': 'application/json' });
    expect(init.body).toBe('{"a":1}');
  });

  it('does not send the token when signing in, or when there is none', async () => {
    const f = jest.fn().mockResolvedValue(res(200, {}));
    await make(f).api.request('/auth/login', { auth: false, body: {} });
    expect(f.mock.calls[0][1].headers.Authorization).toBeUndefined();

    const g = jest.fn().mockResolvedValue(res(200, {}));
    await make(g, null).api.get('/x');
    expect(g.mock.calls[0][1].headers.Authorization).toBeUndefined();
  });

  it('absolute URLs are used as they are (pack downloads)', () => {
    expect(make(jest.fn()).api.url('https://x.test/api/v1/packs/jamb/physics')).toBe('https://x.test/api/v1/packs/jamb/physics');
  });

  it('parses JSON and returns the ETag', async () => {
    const { api } = make(jest.fn().mockResolvedValue(res(200, { exams: [] }, { ETag: '"abc"' })));
    expect(await api.get('/catalog')).toMatchObject({ status: 200, data: { exams: [] }, etag: '"abc"' });
  });

  it('sends If-None-Match and treats 304 as "nothing changed"', async () => {
    const f = jest.fn().mockResolvedValue(new Response(null, { status: 304 }));
    const r = await make(f).api.get('/catalog', { etag: '"abc"' });

    expect(f.mock.calls[0][1].headers['If-None-Match']).toBe('"abc"');
    expect(r).toMatchObject({ status: 304, etag: '"abc"' });
  });

  it('401 tells the app to ask for a new sign-in, but only when a token was sent', async () => {
    const { api, onUnauthorized } = make(jest.fn().mockResolvedValue(res(401, { message: 'Unauthenticated.' })));
    const e = await errorOf(api.get('/me'));
    expect(e).toMatchObject({ kind: 'unauthorized', status: 401 });
    expect(onUnauthorized).toHaveBeenCalledTimes(1);

    const noToken = make(jest.fn().mockResolvedValue(res(401, { message: 'Invalid email or password.' })), null);
    const e2 = await errorOf(noToken.api.post('/auth/login', {}, { auth: false }));
    expect(e2.message).toBe('Invalid email or password.');
    expect(noToken.onUnauthorized).not.toHaveBeenCalled();
  });

  it('422 gives the first field message and all the fields', async () => {
    const { api } = make(jest.fn().mockResolvedValue(res(422, { message: 'x', errors: { email: ['The email has already been taken.'], password: ['Too short.'] } })));
    const e = await errorOf(api.post('/auth/register', {}));

    expect(e).toMatchObject({ kind: 'validation', message: 'The email has already been taken.' });
    expect(e.fields.password).toEqual(['Too short.']);
  });

  it.each([
    [403, 'forbidden'], [429, 'throttled'], [500, 'server'], [503, 'server'], [418, 'unknown'],
  ])('%s becomes %s', async (status, kind) => {
    const { api } = make(jest.fn().mockResolvedValue(res(status as number, { message: 'nope' })));
    expect((await errorOf(api.get('/x'))).kind).toBe(kind);
  });

  it('a dropped connection is "offline", with a friendly message', async () => {
    const { api } = make(jest.fn().mockRejectedValue(new TypeError('Network request failed')));
    const e = await errorOf(api.get('/x'));
    expect(e.kind).toBe('offline');
    expect(e.message).toMatch(/offline/i);
  });

  it('a slow server times out', async () => {
    const slow = jest.fn((_u: string, init: RequestInit) => new Promise((_r, reject) => init.signal?.addEventListener('abort', () => reject(new Error('aborted')))));
    const { api } = make(slow as unknown as typeof fetch);
    expect((await errorOf(api.get('/x'))).kind).toBe('timeout');
  });

  it('survives a non-JSON error body', async () => {
    const { api } = make(jest.fn().mockResolvedValue(res(500, '<html>Bad gateway</html>')));
    expect((await errorOf(api.get('/x'))).kind).toBe('server');
  });
});
