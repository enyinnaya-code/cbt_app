import { kvGet, kvSet } from '@/db/progress';
import type { Db } from '@/db/types';
import { Api } from '@/services/api';
import { CatalogIndex } from '@/services/catalog';
import { cachedProfile, isJoined, join, leave, policyUrl, refreshProfile } from '@/services/rankings';
import { SyncEngine } from '@/services/sync';
import { catalogOf, res } from './helpers/fixtures';
import { memoryDb } from './helpers/sqlite';

let db: Db;
beforeEach(async () => { db = await memoryDb(); });

const profile = (over: object = {}) => ({
  opted_in: false, nickname: null, avatar: null, school: null, avatars: [{ key: 'owl', emoji: 'x' }], privacy_version: '2026-10', privacy_url: 'https://x.test/privacy', ...over,
});

const api = (f: jest.Mock) => new Api({ baseUrl: 'https://x.test/api/v1', getToken: () => 'tok', fetchImpl: f as unknown as typeof fetch });

describe('rankings', () => {
  it('nobody is in the challenge until the server says so', async () => {
    expect(await isJoined(db)).toBe(false);
    expect(await cachedProfile(db)).toBeNull();
  });

  it('remembers the profile so the challenge shows even with no internet', async () => {
    const f = jest.fn().mockResolvedValue(res(200, profile({ opted_in: true, nickname: 'SharpOwl' })));

    await refreshProfile(db, api(f));

    expect(await isJoined(db)).toBe(true);
    expect((await cachedProfile(db))?.nickname).toBe('SharpOwl');
  });

  it('joining sends the nickname, picture, school and the policy consent', async () => {
    const f = jest.fn().mockResolvedValue(res(200, profile({ opted_in: true, nickname: 'SharpOwl', avatar: 'owl' })));

    await join(db, api(f), { nickname: '  SharpOwl ', avatar: 'owl', school: ' ', acceptPrivacy: true });

    const [url, init] = f.mock.calls[0];
    expect(url).toBe('https://x.test/api/v1/leaderboard/profile');
    expect(init.method).toBe('PUT');
    expect(JSON.parse(init.body)).toEqual({ nickname: 'SharpOwl', avatar: 'owl', school: null, accept_privacy: true });
    expect(await isJoined(db)).toBe(true);
  });

  it('leaving takes the student out straight away', async () => {
    await kvSet(db, 'rank.profile', JSON.stringify(profile({ opted_in: true, nickname: 'SharpOwl' })));
    const f = jest.fn().mockResolvedValue(res(200, profile()));

    await leave(db, api(f));

    expect(f.mock.calls[0][1].method).toBe('DELETE');
    expect(await isJoined(db)).toBe(false);
  });

  it('only opens a privacy policy page on our own website', () => {
    expect(policyUrl(api(jest.fn()), { privacy_url: 'https://x.test/privacy' })).toBe('https://x.test/privacy');
    expect(() => policyUrl(api(jest.fn()), { privacy_url: 'https://evil.example/privacy' })).toThrow('did not look safe');
  });
});

describe('study settings follow the student', () => {
  const engine = (f: jest.Mock) => new SyncEngine(db, api(f), () => new CatalogIndex(catalogOf([])));
  const pull = { attempts: [], has_more: false, next_cursor: 0, bookmarks: [], mock_sessions: [], challenges: [], server_time: '2026-10-10T12:00:00+01:00' };

  const server = (settings: object, onPut?: (body: unknown) => void, joined = profile()) => jest.fn(async (url: string, init?: RequestInit) => {
    if (url.endsWith('/me/settings')) {
      if (init?.method === 'PUT') onPut?.(JSON.parse(init.body as string));
      return res(200, settings);
    }
    if (url.endsWith('/leaderboard/profile')) return res(200, joined);
    if (init?.method === 'POST') return res(200, { accepted: {}, rejected: {}, server_time: 'x' });
    return res(200, pull);
  });

  it('takes what the website has when the phone has nothing to send', async () => {
    await kvSet(db, 'settings', JSON.stringify({ theme: 'dark', lang: 'en', targetExam: null }));

    await engine(server({ lang: 'pcm', target_exam: 'jamb', target_exam_date: '2027-04-01' })).run();

    expect(JSON.parse((await kvGet(db, 'settings')) as string)).toMatchObject({ theme: 'dark', lang: 'pcm', targetExam: 'jamb', targetExamDate: '2027-04-01' });
  });

  it('sends a change made on the phone first, then stops flagging it', async () => {
    await kvSet(db, 'settings', JSON.stringify({ lang: 'pcm', targetExam: 'waec', targetExamDate: '2027-05-05' }));
    await kvSet(db, 'settings.dirty', '1');
    let sent: unknown = null;

    await engine(server({ lang: 'en', target_exam: null, target_exam_date: null }, (b) => { sent = b; })).run();

    expect(sent).toEqual({ lang: 'pcm', target_exam: 'waec', target_exam_date: '2027-05-05' });
    expect(await kvGet(db, 'settings.dirty')).toBe('0');
    // The server had older values; they did not overwrite the newer choice made on the phone.
    expect(JSON.parse((await kvGet(db, 'settings')) as string)).toMatchObject({ lang: 'pcm', targetExam: 'waec' });
  });

  it('keeps the flag when offline so the change is sent next time', async () => {
    await kvSet(db, 'settings', JSON.stringify({ lang: 'pcm' }));
    await kvSet(db, 'settings.dirty', '1');
    const f = jest.fn(async (url: string, init?: RequestInit) => {
      if (url.endsWith('/me/settings')) throw new TypeError('Network request failed');
      if (url.endsWith('/leaderboard/profile')) return res(200, profile());
      return init?.method === 'POST' ? res(200, { accepted: {}, rejected: {}, server_time: 'x' }) : res(200, pull);
    });

    await engine(f).run();   // progress sync still completes

    expect(await kvGet(db, 'settings.dirty')).toBe('1');
  });

  it('ignores a malformed reply instead of wiping the choices made on the phone', async () => {
    await kvSet(db, 'settings', JSON.stringify({ lang: 'pcm', targetExam: 'jamb' }));

    await engine(server({ lang: 'klingon', target_exam: 42 })).run();

    expect(JSON.parse((await kvGet(db, 'settings')) as string)).toMatchObject({ lang: 'pcm', targetExam: 'jamb' });
  });

  it('refreshes the ranking profile as part of a sync', async () => {
    const f = server({ lang: 'en', target_exam: null, target_exam_date: null }, undefined, profile({ opted_in: true, nickname: 'Synced' }));

    await engine(f).run();

    expect((await cachedProfile(db))?.nickname).toBe('Synced');
  });
});
