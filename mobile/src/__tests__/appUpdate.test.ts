import { isNewer, parseVersion } from '@/core/version';
import { kvGet, kvSet } from '@/db/progress';
import type { Db } from '@/db/types';
import { Api } from '@/services/api';
import { CHECK_EVERY_MS, checkForUpdate, dismissUpdate, safeUpdateUrl } from '@/services/appUpdate';
import { res } from './helpers/fixtures';
import { memoryDb } from './helpers/sqlite';

let db: Db;
beforeEach(async () => { db = await memoryDb(); });

const api = (f: jest.Mock) => new Api({ baseUrl: 'https://x.test/api/v1', getToken: () => null, fetchImpl: f as unknown as typeof fetch });
const reply = (latest: string | null, url = 'https://x.test/download') => jest.fn().mockResolvedValue(res(200, { latest, update_url: url }));

describe('comparing versions', () => {
  it('reads plain version numbers and nothing else', () => {
    expect(parseVersion('1.1.0')).toEqual([1, 1, 0]);
    expect(parseVersion('v2.0')).toEqual([2, 0]);
    expect(parseVersion('1.2.3-beta')).toEqual([1, 2, 3]);
    for (const bad of ['', 'latest', '1..2', 'one.two', null, undefined, 5, '1.2.3.4.5']) expect(parseVersion(bad)).toBeNull();
  });

  it('knows which is higher, numerically', () => {
    expect(isNewer('1.1.0', '1.0.0')).toBe(true);
    expect(isNewer('1.10.0', '1.9.0')).toBe(true);   // 10 is more than 9, not "1.1"
    expect(isNewer('2.0', '1.9.9')).toBe(true);
    expect(isNewer('1.0.0', '1.0.0')).toBe(false);
    expect(isNewer('1.0', '1.0.0')).toBe(false);
    expect(isNewer('1.0.0', '1.1.0')).toBe(false);
  });

  it('never calls something newer when it cannot read it', () => {
    expect(isNewer('garbage', '1.0.0')).toBe(false);
    expect(isNewer('1.1.0', 'garbage')).toBe(false);
    expect(isNewer(null, '1.0.0')).toBe(false);
  });
});

describe('the update link', () => {
  const base = 'https://x.test/api/v1';

  it('is only our own website or Google Play', () => {
    expect(safeUpdateUrl('https://x.test/download', base)).toBe('https://x.test/download');
    expect(safeUpdateUrl('https://play.google.com/store/apps/details?id=com.testacbt.app', base)).not.toBeNull();
  });

  it('refuses anywhere else, however it is written', () => {
    for (const bad of ['https://evil.example/app.apk', 'https://x.test.evil.example/download', 'https://x.test@evil.example/x', '//evil.example/x', 'javascript:alert(1)', 'market://details?id=bad', '', null, 5]) {
      expect(safeUpdateUrl(bad, base)).toBeNull();
    }
  });
});

describe('the update banner', () => {
  it('appears when the server has a newer version', async () => {
    const offer = await checkForUpdate(db, api(reply('1.1.0')), '1.0.0');

    expect(offer).toEqual({ latest: '1.1.0', url: 'https://x.test/download' });
  });

  it('stays hidden when the phone is up to date, or ahead', async () => {
    expect(await checkForUpdate(db, api(reply('1.1.0')), '1.1.0')).toBeNull();
    await kvSet(db, 'update.checked', '0');
    expect(await checkForUpdate(db, api(reply('1.0.0')), '1.1.0')).toBeNull();
  });

  it('stays hidden when nothing is published or the reply is unusable', async () => {
    expect(await checkForUpdate(db, api(reply(null)), '1.0.0')).toBeNull();
    await kvSet(db, 'update.checked', '0');
    expect(await checkForUpdate(db, api(reply('1.1.0', 'https://evil.example/app.apk')), '1.0.0')).toBeNull();
  });

  it('asks the server only now and then, and remembers the answer', async () => {
    const f = reply('1.1.0');
    const now = 1_000_000_000_000;

    await checkForUpdate(db, api(f), '1.0.0', now);
    await checkForUpdate(db, api(f), '1.0.0', now + 60_000);
    expect(f).toHaveBeenCalledTimes(1);

    const again = await checkForUpdate(db, api(f), '1.0.0', now + CHECK_EVERY_MS + 1);
    expect(f).toHaveBeenCalledTimes(2);
    expect(again?.latest).toBe('1.1.0');
  });

  it('keeps reminding while offline, from the last answer', async () => {
    await checkForUpdate(db, api(reply('1.1.0')), '1.0.0', 1_000);
    const offline = jest.fn().mockRejectedValue(new TypeError('Network request failed'));

    const offer = await checkForUpdate(db, api(offline), '1.0.0', 1_000 + CHECK_EVERY_MS + 5);

    expect(offer?.latest).toBe('1.1.0');
  });

  it('says nothing the very first time when offline', async () => {
    const offline = jest.fn().mockRejectedValue(new TypeError('Network request failed'));

    expect(await checkForUpdate(db, api(offline), '1.0.0')).toBeNull();
  });

  it('later hides this version, but a newer one brings the banner back', async () => {
    const first = await checkForUpdate(db, api(reply('1.1.0')), '1.0.0', 1_000);
    await dismissUpdate(db, first!.latest);

    expect(await checkForUpdate(db, api(reply('1.1.0')), '1.0.0', 2_000)).toBeNull();
    expect(await kvGet(db, 'update.dismissed')).toBe('1.1.0');

    const next = await checkForUpdate(db, api(reply('1.2.0')), '1.0.0', 1_000 + CHECK_EVERY_MS + 1);
    expect(next?.latest).toBe('1.2.0');
  });

  it('is gone once the phone has been updated', async () => {
    await checkForUpdate(db, api(reply('1.1.0')), '1.0.0', 1_000);

    expect(await checkForUpdate(db, api(jest.fn()), '1.1.0', 2_000)).toBeNull();
  });
});
