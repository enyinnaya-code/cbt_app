import { Api } from '@/services/api';
import { closeNotice, currentNotices, safeLink } from '@/services/notices';
import { res } from './helpers/fixtures';
import { memoryDb } from './helpers/sqlite';
import type { Db } from '@/db/types';

const notice = (id: number, over: object = {}) => ({ id, title: `News ${id}`, body: 'Body', tone: 'info', link_url: null, link_label: null, ...over });
const api = (f: jest.Mock) => new Api({ baseUrl: 'https://x.test/api/v1', getToken: () => 'tok', fetchImpl: f as unknown as typeof fetch });

describe('announcements on Home', () => {
  let db: Db;
  beforeEach(async () => { db = await memoryDb(); });

  it('shows what the server sends', async () => {
    const list = await currentNotices(db, api(jest.fn().mockResolvedValue(res(200, { notices: [notice(1), notice(2)] }))));

    expect(list.map((n) => n.id)).toEqual([1, 2]);
  });

  it('keeps a closed announcement closed but still shows a new one', async () => {
    const f = jest.fn().mockResolvedValue(res(200, { notices: [notice(1), notice(2)] }));
    await closeNotice(db, 1);

    expect((await currentNotices(db, api(f))).map((n) => n.id)).toEqual([2]);

    f.mockResolvedValue(res(200, { notices: [notice(3), notice(2), notice(1)] }));
    expect((await currentNotices(db, api(f))).map((n) => n.id)).toEqual([3, 2]);
  });

  it('shows the last list when offline, still without the closed ones', async () => {
    await currentNotices(db, api(jest.fn().mockResolvedValue(res(200, { notices: [notice(1), notice(2)] }))));
    await closeNotice(db, 2);

    const list = await currentNotices(db, api(jest.fn().mockRejectedValue(new TypeError('Network request failed'))));

    expect(list.map((n) => n.id)).toEqual([1]);
  });

  it('shows nothing, not an error, when offline and never loaded or when the reply is not a list', async () => {
    expect(await currentNotices(db, api(jest.fn().mockRejectedValue(new TypeError('Network request failed'))))).toEqual([]);
    expect(await currentNotices(db, api(jest.fn().mockResolvedValue(res(200, { surprise: true }))))).toEqual([]);
  });

  it('only opens web links', () => {
    expect(safeLink('https://testacbt.com/news')).toBe('https://testacbt.com/news');
    expect(safeLink('javascript:alert(1)')).toBeNull();
    expect(safeLink('intent://evil#Intent;end')).toBeNull();
    expect(safeLink(null)).toBeNull();
  });
});
