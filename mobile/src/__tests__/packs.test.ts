import { gzipSync, strToU8 } from 'fflate';
import { createHash } from 'crypto';
import { Api } from '@/services/api';
import { PackError, PackManager, type Downloader, type PackFiles } from '@/services/packs';
import { recordAttempt } from '@/db/progress';
import type { Db } from '@/db/types';
import { gz, instruction, makePack, mcq, subject } from './helpers/fixtures';
import { memoryDb } from './helpers/sqlite';

const sha = async (bytes: Uint8Array) => createHash('sha256').update(bytes).digest('hex');

function setup(over: { download?: Downloader['download'] } = {}) {
  const store = new Map<string, string>();
  const files: PackFiles = {
    write: async (e, s, json) => { store.set(`${e}/${s}`, json); },
    read: async (e, s) => store.get(`${e}/${s}`) ?? null,
    remove: async (e, s) => { store.delete(`${e}/${s}`); },
  };
  const downloader: Downloader = { download: over.download ?? jest.fn() };
  const api = new Api({ baseUrl: 'https://x.test/api/v1', getToken: () => 'tok', fetchImpl: jest.fn() });
  return { store, files, downloader, api };
}

const physics = () => makePack({
  version: 3, topics: [{ id: 7, name: 'Motion' }],
  papers: [{ id: 1, year: 2019, items: [instruction(10), mcq(1, { topic_id: 7 }), mcq(2)] }, { id: 2, year: 2021, items: [mcq(3)] }],
});

const catalogSubject = (bytes: Uint8Array, version = 3) =>
  subject(4, 'physics', { pack: { version, size_bytes: bytes.length, sha256: createHash('sha256').update(bytes).digest('hex'), paper_count: 2, question_count: 3, years: [2021, 2019], built_at: '2026-09-26T10:00:00+01:00', url: '/packs/jamb/physics' } });

let db: Db;
beforeEach(async () => { db = await memoryDb(); });

describe('PackManager.download', () => {
  it('downloads, checks the checksum, unzips and installs', async () => {
    const bytes = gz(physics());
    const { files, api, store } = setup({ download: jest.fn().mockImplementation(async (_u, _h, onProgress) => { onProgress(50, 100); return bytes; }) });
    const m = new PackManager(db, files, { download: jest.fn().mockImplementation(async (_u, _h, onProgress) => { onProgress(50, 100); return bytes; }) }, sha, api, () => new Date('2026-09-26T12:00:00Z'));
    const progress = jest.fn();

    const meta = await m.download('jamb', catalogSubject(bytes), progress);

    expect(meta).toMatchObject({ exam_slug: 'jamb', subject_slug: 'physics', version: 3, question_count: 3, paper_count: 2, years: [2021, 2019], starter: false });
    expect(progress).toHaveBeenCalledWith(50, 100);
    expect(store.has('jamb/physics')).toBe(true);
    expect((await m.installed()).map((p) => p.subject_slug)).toEqual(['physics']);
    expect(await m.usedBytes()).toBe(bytes.length);
  });

  it('asks the server with the sign-in token, using the pack URL from the catalog', async () => {
    const bytes = gz(physics());
    const dl = jest.fn().mockResolvedValue(bytes);
    const { files, api } = setup();
    await new PackManager(db, files, { download: dl }, sha, api).download('jamb', catalogSubject(bytes));

    expect(dl.mock.calls[0][0]).toBe('https://x.test/api/v1/packs/jamb/physics');
    expect(dl.mock.calls[0][1]).toMatchObject({ Authorization: 'Bearer tok' });
  });

  it('refuses a damaged download and installs nothing', async () => {
    const bytes = gz(physics());
    const damaged = bytes.slice();
    damaged[damaged.length - 12] ^= 0xff;
    const { files, api, store } = setup();
    const m = new PackManager(db, files, { download: jest.fn().mockResolvedValue(damaged) }, sha, api);

    await expect(m.download('jamb', catalogSubject(bytes))).rejects.toMatchObject({ kind: 'checksum' });
    expect(store.size).toBe(0);
    expect(await m.installed()).toHaveLength(0);
  });

  it('reports a failed connection in plain words', async () => {
    const { files, api } = setup();
    const m = new PackManager(db, files, { download: jest.fn().mockRejectedValue(new Error('socket hang up')) }, sha, api);

    const e = (await m.download('jamb', catalogSubject(gz(physics()))).catch((x) => x)) as PackError;
    expect(e).toMatchObject({ kind: 'download' });
    expect(e.message).toMatch(/connection/i);
  });

  it('can be cancelled', async () => {
    const controller = new AbortController();
    const { files, api } = setup();
    const m = new PackManager(db, files, { download: jest.fn().mockImplementation(async () => { controller.abort(); throw new Error('aborted'); }) }, sha, api);

    await expect(m.download('jamb', catalogSubject(gz(physics())), () => {}, controller.signal)).rejects.toMatchObject({ kind: 'cancelled' });
  });

  it('rejects a file that is not gzip even when the checksum matches', async () => {
    const junk = strToU8('this is not gzip');
    const { files, api } = setup();
    const m = new PackManager(db, files, { download: jest.fn().mockResolvedValue(junk) }, sha, api);

    await expect(m.download('jamb', catalogSubject(junk))).rejects.toMatchObject({ kind: 'format' });
  });

  it('has nothing to download when the catalog has no pack', async () => {
    const { files, api, downloader } = setup();
    await expect(new PackManager(db, files, downloader, sha, api).download('jamb', subject(4, 'physics'))).rejects.toMatchObject({ kind: 'download' });
  });
});

describe('PackManager.installFromText', () => {
  const build = () => { const { files, api, downloader } = setup(); return new PackManager(db, files, downloader, sha, api); };

  it('rejects files that are not packs', async () => {
    const m = build();
    await expect(m.installFromText('not json')).rejects.toMatchObject({ kind: 'format' });
    await expect(m.installFromText('{"format":1}')).rejects.toMatchObject({ kind: 'format' });
    await expect(m.installFromText(JSON.stringify({ ...physics(), format: 0 }))).rejects.toMatchObject({ kind: 'format' });
  });

  it('asks the student to update the app when the pack is from the future', async () => {
    const e = (await build().installFromText(JSON.stringify({ ...physics(), format: 2 })).catch((x) => x)) as PackError;
    expect(e).toMatchObject({ kind: 'newer-app-needed' });
    expect(e.message).toMatch(/update/i);
  });

  it('rejects a pack for a different subject than the one chosen', async () => {
    await expect(build().installFromText(JSON.stringify(physics()), { expectExam: 'jamb', expectSubject: 'chemistry' })).rejects.toMatchObject({ kind: 'format' });
  });

  it('a newer version replaces the old one', async () => {
    const m = build();
    await m.installFromText(JSON.stringify(physics()), { size: 100 });
    await m.installFromText(JSON.stringify({ ...physics(), version: 4 }), { size: 200 });

    const all = await m.installed();
    expect(all).toHaveLength(1);
    expect(all[0]).toMatchObject({ version: 4, size_bytes: 200 });
  });

  it('a downloaded pack replaces the bundled starter', async () => {
    const m = build();
    await m.installFromText(JSON.stringify({ ...physics(), version: 0 }), { starter: true });
    expect((await m.meta('jamb', 'physics'))?.starter).toBe(true);

    await m.installFromText(JSON.stringify(physics()), { starter: false });
    expect((await m.meta('jamb', 'physics'))?.starter).toBe(false);
  });

  it('records which tier a pack is, and treats the bundled starter as a sample', async () => {
    const m = build();

    await m.installFromText(JSON.stringify({ ...physics(), tier: 'free' }));
    expect((await m.meta('jamb', 'physics'))?.tier).toBe('free');

    await m.installFromText(JSON.stringify({ ...physics(), tier: 'full' }));
    expect((await m.meta('jamb', 'physics'))?.tier).toBe('full');

    await m.installFromText(JSON.stringify(physics()));
    expect((await m.meta('jamb', 'physics'))?.tier).toBe('full');   // packs from before samples existed have no tier

    await m.installFromText(JSON.stringify(physics()), { starter: true });
    expect((await m.meta('jamb', 'physics'))?.tier).toBe('free');
  });

  it('offers the other tier as an update even when its version number is lower', async () => {
    const m = build();
    await m.installFromText(JSON.stringify({ ...physics(), tier: 'free', version: 7 }));
    const unlocked = subject(4, 'physics', { pack: { tier: 'full', version: 2, size_bytes: 1, sha256: 'a', paper_count: 1, question_count: 1, years: [], built_at: '', url: '/x' } });

    expect((await m.updatesAvailable('jamb', [unlocked])).map((s) => s.slug)).toEqual(['physics']);
  });

  it('gives topic names to answers restored from the server, which only knew the ids', async () => {
    await recordAttempt(db, { uuid: 'r', questionId: 1, examId: 3, examSlug: 'jamb', subjectId: 4, subjectSlug: 'physics', subjectName: 'Physics', year: 2019, topicId: 7, topicName: null, mode: 'practice', selected: 'B', isCorrect: true, timeMs: null, answeredAt: '2026-09-26T10:00:00.000Z', synced: true });

    await build().installFromText(JSON.stringify(physics()));

    expect((await db.getFirstAsync<{ topic_name: string }>("SELECT topic_name FROM attempts WHERE uuid = 'r'"))?.topic_name).toBe('Motion');
  });
});

describe('PackManager load, remove and updates', () => {
  it('loads an installed pack and caches it', async () => {
    const { files, api, downloader } = setup();
    const read = jest.spyOn(files, 'read');
    const m = new PackManager(db, files, downloader, sha, api);
    await m.installFromText(JSON.stringify(physics()));

    const a = await m.load('jamb', 'physics');
    const b = await m.load('jamb', 'physics');

    expect(a?.papers).toHaveLength(2);
    expect(b).toBe(a);
    expect(read).toHaveBeenCalledTimes(1);
    expect(await m.load('jamb', 'chemistry')).toBeNull();
  });

  it('loads the new content after an update', async () => {
    const { files, api, downloader } = setup();
    const m = new PackManager(db, files, downloader, sha, api);
    await m.installFromText(JSON.stringify(physics()));
    await m.load('jamb', 'physics');

    await m.installFromText(JSON.stringify({ ...physics(), version: 9, papers: [] }));

    expect((await m.load('jamb', 'physics'))?.papers).toHaveLength(0);
  });

  it('removes the file and the record', async () => {
    const { files, api, downloader, store } = setup();
    const m = new PackManager(db, files, downloader, sha, api);
    await m.installFromText(JSON.stringify(physics()));

    await m.remove('jamb', 'physics');

    expect(store.size).toBe(0);
    expect(await m.installed()).toHaveLength(0);
    expect(await m.load('jamb', 'physics')).toBeNull();
  });

  it('finds packs with a newer version, and always offers to replace the starter', async () => {
    const { files, api, downloader } = setup();
    const m = new PackManager(db, files, downloader, sha, api);
    await m.installFromText(JSON.stringify({ ...physics(), version: 2 }));
    await m.installFromText(JSON.stringify({ ...makePack({ subject: 'chemistry', papers: [{ id: 5, year: 2020, items: [mcq(70)] }], version: 0 }) }), { starter: true });

    const pack = (v: number) => ({ version: v, size_bytes: 1, sha256: 'a', paper_count: 1, question_count: 1, years: [2020], built_at: '', url: '/x' });
    const updates = await m.updatesAvailable('jamb', [
      subject(4, 'physics', { pack: pack(3) }),          // newer than 2
      subject(5, 'chemistry', { pack: pack(1) }),        // starter is version 0 but always replaceable
      subject(6, 'biology', { pack: pack(1) }),          // not installed: not an update
    ]);

    expect(updates.map((s) => s.slug)).toEqual(['physics', 'chemistry']);
    expect((await m.updatesAvailable('jamb', [subject(4, 'physics', { pack: pack(2) })]))).toHaveLength(0);
  });
});

describe('gzip of large packs', () => {
  it('handles a pack with a big embedded image', async () => {
    const big = makePack({ papers: [{ id: 1, year: 2020, items: [mcq(1, { html: `<p><img src="data:image/png;base64,${'A'.repeat(300000)}"></p>` })] }] });
    const bytes = gzipSync(strToU8(JSON.stringify(big)));
    const { files, api } = setup();
    const m = new PackManager(db, files, { download: jest.fn().mockResolvedValue(bytes) }, sha, api);

    const meta = await m.download('jamb', catalogSubject(bytes, 1));

    expect(meta.question_count).toBe(1);
  });
});
