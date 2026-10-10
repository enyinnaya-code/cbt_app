import { SMALL_PACK_BYTES, endingSoon, pendingUpgrades, replaceNow } from '@/core/purchases';
import { buildViews, type InstalledInfo } from '@/core/views';
import { kvSet } from '@/db/progress';
import type { Db } from '@/db/types';
import { Api } from '@/services/api';
import { cachedPurchases, loadPurchases } from '@/services/purchases';
import { catalogOf, res, subject } from './helpers/fixtures';
import { memoryDb } from './helpers/sqlite';

const pack = (tier: 'free' | 'full', bytes = 5000) => ({ tier, version: 1, size_bytes: bytes, sha256: 'a', paper_count: 1, question_count: tier === 'free' ? 30 : 50, years: [2020], built_at: '', url: '/x' });
const inst = (tier: 'free' | 'full', over: Partial<InstalledInfo> = {}): InstalledInfo => ({
  exam_slug: 'waec', subject_slug: 'crs', exam_name: 'WAEC', subject_name: 'CRS', display_name: 'CRS', tier, version: 1, size_bytes: 500, question_count: tier === 'free' ? 30 : 50, years: [2020], starter: false, ...over,
});
const catalogWith = (over: object) => catalogOf([{ id: 1, slug: 'waec', name: 'WAEC', subjects: [subject(9, 'crs', { code: 'CR', ...over })] }]);

describe('replacing the free sample after a purchase', () => {
  it('goes straight away on Wi-Fi, when Wi-Fi only is off, or when the set is small', () => {
    expect(replaceNow(60_000_000, true, true)).toBe(true);
    expect(replaceNow(60_000_000, false, false)).toBe(true);
    expect(replaceNow(SMALL_PACK_BYTES, true, false)).toBe(true);
  });

  it('waits only when the set is big, the phone is on mobile data and the student asked to save data', () => {
    expect(replaceNow(SMALL_PACK_BYTES + 1, true, false)).toBe(false);
  });

  it('a small full set is no longer held back by the Wi-Fi only setting (the 30 versus 50 questions case)', () => {
    // CRS full pack: a few KB. Default settings: Wi-Fi only on, phone on mobile data.
    expect(replaceNow(4_000, true, false)).toBe(true);
  });
});

describe('what was bought but is not on the phone yet', () => {
  it('lists an unlocked subject whose phone copy is still the free sample', () => {
    const views = buildViews(catalogWith({ access: 'full', pack: pack('full') }), [inst('free')]);

    expect(pendingUpgrades(views).map((s) => s.slug)).toEqual(['crs']);
  });

  it('lists nothing once the full set is installed, or while the subject is still locked, or for bundled starter questions', () => {
    expect(pendingUpgrades(buildViews(catalogWith({ access: 'full', pack: pack('full') }), [inst('full')]))).toEqual([]);
    expect(pendingUpgrades(buildViews(catalogWith({ access: 'free', pack: pack('free') }), [inst('free')]))).toEqual([]);
    expect(pendingUpgrades(buildViews(catalogWith({ access: 'full', pack: pack('full') }), [inst('free', { starter: true })]))).toEqual([]);
    expect(pendingUpgrades(buildViews(catalogWith({ access: 'full', pack: pack('full') }), []))).toEqual([]);
  });
});

describe('reminders before a subject ends', () => {
  const now = new Date('2026-10-10T12:00:00Z');
  const ends = (days: number) => new Date(now.getTime() + days * 86_400_000).toISOString();

  it('lists subjects ending within two weeks, soonest first, counting part of a day as a day', () => {
    const catalog = catalogOf([{ id: 1, slug: 'waec', name: 'WAEC', subjects: [
      subject(1, 'physics', { expires_at: ends(9.2) }), subject(2, 'crs', { expires_at: ends(2) }), subject(3, 'maths', { expires_at: ends(60) }),
    ] }]);

    expect(endingSoon(catalog, now).map((e) => [e.subjectSlug, e.days])).toEqual([['crs', 2], ['physics', 10]]);
  });

  it('ignores subjects already ended, never bought, or with a broken date', () => {
    const catalog = catalogOf([{ id: 1, slug: 'waec', name: 'WAEC', subjects: [
      subject(1, 'physics', { expires_at: ends(-3) }), subject(2, 'crs', { expires_at: null }), subject(3, 'maths', { expires_at: 'soon' }),
    ] }]);

    expect(endingSoon(catalog, now)).toEqual([]);
    expect(endingSoon(null, now)).toEqual([]);
  });
});

describe('the purchases list', () => {
  let db: Db;
  beforeEach(async () => { db = await memoryDb(); });

  const body = { remind_days: 14, unlocked: [{ exam: 'WAEC', exam_slug: 'waec', subject: 'CRS', subject_slug: 'crs', expires_at: '2027-01-01T00:00:00Z', days_left: 83, expired: false }], payments: [] };
  const api = (f: jest.Mock) => new Api({ baseUrl: 'https://x.test/api/v1', getToken: () => 'tok', fetchImpl: f as unknown as typeof fetch });

  it('is fetched and remembered', async () => {
    const r = await loadPurchases(db, api(jest.fn().mockResolvedValue(res(200, body))));

    expect(r).toEqual({ data: body, stale: false });
    expect(await cachedPurchases(db)).toEqual(body);
  });

  it('falls back to the last list, marked as old, when offline', async () => {
    await loadPurchases(db, api(jest.fn().mockResolvedValue(res(200, body))));

    const r = await loadPurchases(db, api(jest.fn().mockRejectedValue(new TypeError('Network request failed'))));

    expect(r).toEqual({ data: body, stale: true });
  });

  it('shows nothing, not an error, when offline and never loaded', async () => {
    const r = await loadPurchases(db, api(jest.fn().mockRejectedValue(new TypeError('Network request failed'))));

    expect(r).toEqual({ data: null, stale: true });
  });

  it('ignores a reply that is not a purchases list', async () => {
    await kvSet(db, 'purchases.cache', JSON.stringify(body));

    const r = await loadPurchases(db, api(jest.fn().mockResolvedValue(res(200, { surprise: true }))));

    expect(r.data).toEqual(body);
    expect(r.stale).toBe(true);
  });
});
