import * as P from '@/db/progress';
import type { Db } from '@/db/types';
import { Api } from '@/services/api';
import { CatalogIndex } from '@/services/catalog';
import { SyncEngine } from '@/services/sync';
import { catalogOf, res, subject } from './helpers/fixtures';
import { memoryDb } from './helpers/sqlite';

const index = new CatalogIndex(catalogOf([
  { id: 3, slug: 'jamb', name: 'JAMB', subjects: [subject(4, 'physics', { display_name: 'Physics' }), subject(1, 'english-language', { display_name: 'Use of English' })] },
]));

let db: Db;
beforeEach(async () => { db = await memoryDb(); });

const engine = (f: jest.Mock) => new SyncEngine(db, new Api({ baseUrl: 'https://x.test/api/v1', getToken: () => 'tok', fetchImpl: f as unknown as typeof fetch }), () => index);

const emptyPull = { attempts: [], has_more: false, next_cursor: 0, bookmarks: [], mock_sessions: [], server_time: '2026-09-26T12:00:00+01:00' };
const ok = () => res(200, { accepted: {}, rejected: {}, server_time: 'x' });

const att = (uuid: string, over: Partial<P.AttemptInput> = {}): P.AttemptInput => ({
  uuid, questionId: 5, examId: 3, examSlug: 'jamb', subjectId: 4, subjectSlug: 'physics', subjectName: 'Physics', year: 2020, topicId: null, topicName: null,
  mode: 'practice', selected: 'B', isCorrect: true, timeMs: 1200, answeredAt: '2026-09-26T10:00:00.000Z', ...over,
});

/** Answers POST /sync/progress with `postReply` and GET with each page in turn. */
function server(pages: object[] = [emptyPull], postReply: () => Response = ok) {
  let page = 0;
  return jest.fn(async (url: string, init?: RequestInit) => (init?.method === 'POST' ? postReply() : res(200, pages[Math.min(page++, pages.length - 1)])));
}

const posts = (f: jest.Mock) => f.mock.calls.filter(([, init]) => init?.method === 'POST').map(([, init]) => JSON.parse(init.body));

describe('push', () => {
  it('uploads waiting answers, bookmarks and mock results, then marks them sent', async () => {
    await P.recordAttempt(db, att('a1'));
    await P.setBookmark(db, { questionId: 5, examId: 3, subjectId: 4, examSlug: 'jamb', subjectSlug: 'physics', bookmarked: true, changedAt: '2026-09-26T10:05:00.000Z' });
    await P.saveMock(db, { uuid: 'm1', examId: 3, examName: 'JAMB', score: 250, total: 400, durationSeconds: 3000, takenAt: '2026-09-26T11:00:00.000Z', subjectScores: [{ subject_id: 1, correct: 30, total: 60 }] });
    const f = server();

    const r = await engine(f).run();

    expect(r.pushed).toEqual({ attempts: 1, bookmarks: 1, mocks: 1 });
    const [body] = posts(f);
    expect(body.attempts).toEqual([{ client_uuid: 'a1', question_id: 5, mode: 'practice', selected: 'B', time_ms: 1200, answered_at: '2026-09-26T10:00:00.000Z' }]);
    expect(body.bookmarks).toEqual([{ question_id: 5, bookmarked: true, changed_at: '2026-09-26T10:05:00.000Z' }]);
    expect(body.mock_sessions[0]).toMatchObject({ client_uuid: 'm1', exam_id: 3, score: 250, total: 400, subject_scores: [{ subject_id: 1, correct: 30, total: 60 }] });
    expect(await engine(server()).pending()).toBe(0);
  });

  it('sends nothing when there is nothing to send', async () => {
    const f = server();
    await engine(f).run();
    expect(posts(f)).toHaveLength(0);
  });

  it('never sends the bundled starter questions', async () => {
    await P.recordAttempt(db, att('s1', { questionId: -4 }));
    const f = server();

    await engine(f).run();

    expect(posts(f)).toHaveLength(0);
    expect(await engine(f).pending()).toBe(0);
  });

  it('works through a backlog in batches', async () => {
    for (let i = 0; i < 450; i++) await P.recordAttempt(db, att(`b${i}`, { answeredAt: new Date(2026, 8, 1, 10, 0, i).toISOString() }));
    const f = server();

    const r = await engine(f).run();

    expect(posts(f).map((b) => b.attempts.length)).toEqual([200, 200, 50]);
    expect(r.pushed.attempts).toBe(450);
    expect(await engine(server()).pending()).toBe(0);
  });

  it('keeps everything when the connection drops, and sends it next time', async () => {
    await P.recordAttempt(db, att('a1'));
    const down = jest.fn().mockRejectedValue(new TypeError('Network request failed'));

    await expect(engine(down).run()).rejects.toMatchObject({ kind: 'offline' });
    expect(await engine(down).pending()).toBe(1);

    const up = server();
    await engine(up).run();
    expect(posts(up)[0].attempts[0].client_uuid).toBe('a1');
    expect(await engine(up).pending()).toBe(0);
  });

  it('does not double count after a dropped reply: resending the same answers is harmless', async () => {
    await P.recordAttempt(db, att('a1'));
    const first = jest.fn(async (_u: string, init?: RequestInit) => { if (init?.method === 'POST') throw new TypeError('reply lost'); return res(200, emptyPull); });
    await expect(engine(first).run()).rejects.toBeDefined();

    const second = server();
    await engine(second).run();

    expect(posts(second)[0].attempts.map((a: { client_uuid: string }) => a.client_uuid)).toEqual(['a1']);   // same uuid: the server stores it once
  });

  it('drops a batch the server will never accept so it cannot block everything behind it', async () => {
    await P.recordAttempt(db, att('bad'));
    const f = server([emptyPull], () => res(422, { message: 'Invalid', errors: { 'attempts.0.selected': ['bad'] } }));

    await engine(f).run();

    expect(await engine(f).pending()).toBe(0);
  });

  it('stops and reports when the sign-in has expired, keeping the answers', async () => {
    await P.recordAttempt(db, att('a1'));
    const f = server([emptyPull], () => res(401, { message: 'Unauthenticated.' }));

    await expect(engine(f).run()).rejects.toMatchObject({ kind: 'unauthorized' });
    expect(await engine(f).pending()).toBe(1);
  });

  it('only one sync runs at a time; a second caller shares the result', async () => {
    await P.recordAttempt(db, att('a1'));
    const f = server();
    const e = engine(f);

    const [a, b] = await Promise.all([e.run(), e.run()]);

    expect(a).toBe(b);
    expect(posts(f)).toHaveLength(1);
  });
});

describe('pull', () => {
  const pulled = (n: number, start = 1) => Array.from({ length: n }, (_, i) => ({
    id: start + i, client_uuid: `p${start + i}`, question_id: 100 + i, mode: 'practice', selected: 'B', is_correct: i % 2 === 0, time_ms: null,
    answered_at: '2026-09-25T10:00:00+01:00', exam_id: 3, subject_id: 4, year: 2019, topic_id: 7,
  }));

  it('restores answers, bookmarks and mock results on a new phone, filed under the right subject', async () => {
    const f = server([{
      attempts: pulled(3), has_more: false, next_cursor: 3,
      bookmarks: [{ question_id: 100, bookmarked: true, changed_at: '2026-09-25T10:00:00+01:00', exam_id: 3, subject_id: 4 }],
      mock_sessions: [{ client_uuid: 'm9', exam_id: 3, subject_scores: [{ subject_id: 1, correct: 30, total: 60 }], score: 240, total: 400, duration_seconds: 5000, taken_at: '2026-09-24T10:00:00+01:00' }],
      server_time: '2026-09-26T12:00:00+01:00',
    }]);

    const r = await engine(f).run();

    expect(r.pulled).toEqual({ attempts: 3, bookmarks: 1, mocks: 1 });
    expect(await P.totals(db)).toEqual({ answered: 3, correct: 2 });
    expect((await P.subjectStats(db))[0]).toMatchObject({ subject_slug: 'physics', subject_name: 'Physics', answered: 3 });
    expect((await P.bookmarks(db))[0]).toMatchObject({ question_id: 100, exam_slug: 'jamb', subject_slug: 'physics' });
    expect((await P.mocks(db))[0]).toMatchObject({ uuid: 'm9', score: 240, exam_name: 'JAMB' });
    expect(await engine(f).pending()).toBe(0);   // restored records are not sent back
  });

  it('follows pages using the cursor, then only asks for what is new', async () => {
    const f = server([
      { ...emptyPull, attempts: pulled(2, 1), has_more: true, next_cursor: 2 },
      { ...emptyPull, attempts: pulled(1, 3), has_more: false, next_cursor: 3 },
    ]);

    await engine(f).run();

    const urls = f.mock.calls.filter(([, init]) => !init?.method || init.method === 'GET').map(([u]) => u as string);
    expect(urls[0]).toContain('cursor=0');
    expect(urls[1]).toContain('cursor=2');
    expect(await P.totals(db)).toEqual({ answered: 3, correct: 2 });

    const g = server();
    await engine(g).run();
    const next = g.mock.calls[0][0] as string;
    expect(next).toContain('cursor=3');
    expect(next).toContain('since=');
  });

  it('is safe to run twice: the same answers are not added again', async () => {
    const page = { ...emptyPull, attempts: pulled(2), next_cursor: 2 };
    await engine(server([page])).run();
    await P.kvSet(db, 'sync.cursor', '0');   // pretend the cursor was lost
    await engine(server([page])).run();

    expect((await P.totals(db)).answered).toBe(2);
  });

  it('keeps a newer bookmark change made on this phone over an older one from the server', async () => {
    await P.setBookmark(db, { questionId: 100, examId: 3, subjectId: 4, examSlug: 'jamb', subjectSlug: 'physics', bookmarked: false, changedAt: '2026-09-26T09:00:00.000Z' });
    const f = server([{ ...emptyPull, bookmarks: [{ question_id: 100, bookmarked: true, changed_at: '2026-09-25T09:00:00+01:00', exam_id: 3, subject_id: 4 }] }]);

    await engine(f).run();

    expect((await P.bookmarkedIds(db)).size).toBe(0);
  });

  it('a failed pull after a successful upload still counts as uploaded', async () => {
    await P.recordAttempt(db, att('a1'));
    const f = jest.fn(async (_u: string, init?: RequestInit) => { if (init?.method === 'POST') return ok(); throw new TypeError('offline'); });

    await expect(engine(f as unknown as jest.Mock).run()).rejects.toMatchObject({ kind: 'offline' });
    expect(await engine(f as unknown as jest.Mock).pending()).toBe(0);
  });
});
