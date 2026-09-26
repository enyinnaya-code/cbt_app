import * as P from '@/db/progress';
import { migrate, MIGRATIONS, SCHEMA_VERSION } from '@/db/schema';
import type { Db } from '@/db/types';
import { memoryDb, rawDb } from './helpers/sqlite';

let n = 0;
const attempt = (over: Partial<P.AttemptInput> = {}): P.AttemptInput => ({
  uuid: `u-${++n}`, questionId: 1, examId: 3, examSlug: 'jamb', subjectId: 4, subjectSlug: 'physics', subjectName: 'Physics', year: 2020,
  topicId: null, topicName: null, mode: 'practice', selected: 'B', isCorrect: true, timeMs: 5000, answeredAt: '2026-09-26T10:00:00.000Z', ...over,
});

let db: Db;
beforeEach(async () => { db = await memoryDb(); });

describe('migrations', () => {
  it('create the schema and can run again without harm', async () => {
    const fresh = rawDb();
    await migrate(fresh);
    await migrate(fresh);

    expect((await fresh.getFirstAsync<{ user_version: number }>('PRAGMA user_version'))?.user_version).toBe(SCHEMA_VERSION);
    const tables = (await fresh.getAllAsync<{ name: string }>("SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name")).map((t) => t.name);
    expect(tables).toEqual(expect.arrayContaining(['attempts', 'bookmarks', 'kv', 'mock_runs', 'mocks', 'packs']));
  });

  it('upgrade a phone that already has packs: they become full packs, except the bundled starter which is a sample', async () => {
    const old = rawDb();
    await old.execAsync(MIGRATIONS[0]);   // the first release
    await old.execAsync('PRAGMA user_version = 1');
    const add = (subject: string, starter: number) => old.runAsync(
      `INSERT INTO packs (exam_slug, subject_slug, version, size_bytes, question_count, paper_count, years, starter, installed_at) VALUES ('jamb', ?, 1, 10, 5, 1, '[2020]', ?, '2026-09-26T10:00:00Z')`, [subject, starter]);
    await add('physics', 0);
    await add('biology', 1);

    await migrate(old);

    const tiers = await old.getAllAsync<{ subject_slug: string; tier: string }>('SELECT subject_slug, tier FROM packs ORDER BY subject_slug');
    expect(tiers).toEqual([{ subject_slug: 'biology', tier: 'free' }, { subject_slug: 'physics', tier: 'full' }]);
  });
});

describe('attempts', () => {
  it('are stored once, however many times the same answer arrives', async () => {
    const a = attempt();
    expect(await P.recordAttempt(db, a)).toBe(true);
    expect(await P.recordAttempt(db, a)).toBe(false);
    expect((await P.totals(db)).answered).toBe(1);
  });

  it('only real questions are queued for upload, in order, and can be marked as sent', async () => {
    await P.recordAttempt(db, attempt({ uuid: 'late', answeredAt: '2026-09-26T12:00:00.000Z' }));
    await P.recordAttempt(db, attempt({ uuid: 'early', answeredAt: '2026-09-26T09:00:00.000Z' }));
    await P.recordAttempt(db, attempt({ uuid: 'starter', questionId: -5 }));   // bundled starter question: never sent
    await P.recordAttempt(db, attempt({ uuid: 'restored', synced: true }));     // came from the server

    expect((await P.unsyncedAttempts(db)).map((a) => a.uuid)).toEqual(['early', 'late']);

    await P.markAttemptsSynced(db, ['early']);
    expect((await P.unsyncedAttempts(db)).map((a) => a.uuid)).toEqual(['late']);
  });

  it('marking many at once works past SQLite\'s parameter comfort zone', async () => {
    const ids = Array.from({ length: 450 }, (_, i) => `m-${i}`);
    for (const uuid of ids) await P.recordAttempt(db, attempt({ uuid }));

    await P.markAttemptsSynced(db, ids);

    expect(await P.unsyncedAttempts(db, 1000)).toHaveLength(0);
  });
});

describe('stats', () => {
  it('total answered and correct', async () => {
    await P.recordAttempt(db, attempt({ isCorrect: true }));
    await P.recordAttempt(db, attempt({ isCorrect: false, selected: 'A' }));
    expect(await P.totals(db)).toEqual({ answered: 2, correct: 1 });
    expect(await P.totals(await memoryDb())).toEqual({ answered: 0, correct: 0 });
  });

  it('subjects are ranked weakest first', async () => {
    for (const ok of [true, true, false]) await P.recordAttempt(db, attempt({ subjectId: 4, subjectSlug: 'physics', subjectName: 'Physics', isCorrect: ok }));
    for (const ok of [false, false]) await P.recordAttempt(db, attempt({ subjectId: 5, subjectSlug: 'maths', subjectName: 'Mathematics', isCorrect: ok }));

    const s = await P.subjectStats(db);

    expect(s.map((x) => [x.subject_name, x.answered, x.correct, x.accuracy])).toEqual([['Mathematics', 2, 0, 0], ['Physics', 3, 2, 67]]);
  });

  it('weak topics need enough answers and skip topics whose name is not known yet', async () => {
    for (const ok of [true, false, false, false, false]) await P.recordAttempt(db, attempt({ topicId: 7, topicName: 'Heat', isCorrect: ok }));
    for (let i = 0; i < 4; i++) await P.recordAttempt(db, attempt({ topicId: 8, topicName: 'Light', isCorrect: false }));   // only 4 answers
    for (let i = 0; i < 6; i++) await P.recordAttempt(db, attempt({ topicId: 9, topicName: null, isCorrect: false }));    // restored, name unknown

    const t = await P.weakTopics(db);

    expect(t.map((x) => [x.topic_name, x.accuracy])).toEqual([['Heat', 20]]);
  });

  it('streak counts consecutive local days and survives until the day is over', async () => {
    const at = (y: number, m: number, d: number, h = 10) => new Date(y, m - 1, d, h).toISOString();
    expect(await P.streak(db, new Date(2026, 8, 26, 9))).toBe(0);

    await P.recordAttempt(db, attempt({ answeredAt: at(2026, 9, 25) }));
    await P.recordAttempt(db, attempt({ answeredAt: at(2026, 9, 24) }));
    expect(await P.streak(db, new Date(2026, 8, 26, 9))).toBe(2);     // not studied yet today: still alive

    await P.recordAttempt(db, attempt({ answeredAt: at(2026, 9, 26) }));
    await P.recordAttempt(db, attempt({ answeredAt: at(2026, 9, 26, 15) }));   // several answers in a day count once
    expect(await P.streak(db, new Date(2026, 8, 26, 20))).toBe(3);

    await P.recordAttempt(db, attempt({ answeredAt: at(2026, 9, 21) }));       // a gap ends it
    expect(await P.streak(db, new Date(2026, 8, 26, 20))).toBe(3);
  });

  it('a missed day ends the streak', async () => {
    await P.recordAttempt(db, attempt({ answeredAt: new Date(2026, 8, 23, 10).toISOString() }));
    expect(await P.streak(db, new Date(2026, 8, 26, 9))).toBe(0);
  });

  it('last seven days fills in quiet days and marks today', async () => {
    const at = (d: number) => new Date(2026, 8, d, 11).toISOString();
    await P.recordAttempt(db, attempt({ answeredAt: at(26) }));
    await P.recordAttempt(db, attempt({ answeredAt: at(26) }));
    await P.recordAttempt(db, attempt({ answeredAt: at(24) }));
    await P.recordAttempt(db, attempt({ answeredAt: at(10) }));   // too old

    const days = await P.lastDays(db, 7, new Date(2026, 8, 26, 12));

    expect(days).toHaveLength(7);
    expect(days.map((d) => d.count)).toEqual([0, 0, 0, 0, 1, 0, 2]);
    expect(days[6]).toMatchObject({ today: true, label: 'Sat' });
  });

  it('remembers the last subject practised, ignoring mocks', async () => {
    await P.recordAttempt(db, attempt({ subjectSlug: 'physics', subjectName: 'Physics', answeredAt: '2026-09-26T08:00:00.000Z' }));
    await P.recordAttempt(db, attempt({ subjectSlug: 'chemistry', subjectName: 'Chemistry', answeredAt: '2026-09-26T09:00:00.000Z' }));
    await P.recordAttempt(db, attempt({ subjectSlug: 'biology', subjectName: 'Biology', mode: 'mock', answeredAt: '2026-09-26T10:00:00.000Z' }));

    expect((await P.lastPractised(db))?.subject_slug).toBe('chemistry');
  });
});

describe('bookmarks', () => {
  const b = (over: Partial<P.BookmarkInput> = {}): P.BookmarkInput => ({
    questionId: 10, examId: 3, subjectId: 4, examSlug: 'jamb', subjectSlug: 'physics', bookmarked: true, changedAt: '2026-09-26T10:00:00.000Z', ...over,
  });

  it('save and remove, with the newest change winning', async () => {
    await P.setBookmark(db, b());
    expect([...(await P.bookmarkedIds(db))]).toEqual([10]);

    await P.setBookmark(db, b({ bookmarked: false, changedAt: '2026-09-26T11:00:00.000Z' }));
    expect((await P.bookmarkedIds(db)).size).toBe(0);

    // An older change arriving late (say, restored from the server) must not undo the newer one.
    await P.setBookmark(db, b({ bookmarked: true, changedAt: '2026-09-26T09:00:00.000Z', synced: true }));
    expect((await P.bookmarkedIds(db)).size).toBe(0);
  });

  it('keep where the question came from when a later change lacks it', async () => {
    await P.setBookmark(db, b());
    await P.setBookmark(db, b({ examId: null, subjectId: null, examSlug: null, subjectSlug: null, bookmarked: true, changedAt: '2026-09-26T12:00:00.000Z' }));

    const [row] = await P.bookmarks(db);
    expect([row.exam_slug, row.subject_slug, row.subject_id]).toEqual(['jamb', 'physics', 4]);
  });

  it('are queued for upload until marked sent, and starter questions never are', async () => {
    await P.setBookmark(db, b({ questionId: 1 }));
    await P.setBookmark(db, b({ questionId: 2, synced: true }));
    await P.setBookmark(db, b({ questionId: -3 }));

    expect((await P.unsyncedBookmarks(db)).map((x) => x.question_id)).toEqual([1]);
    await P.markBookmarksSynced(db, [1]);
    expect(await P.unsyncedBookmarks(db)).toHaveLength(0);
  });
});

describe('mocks', () => {
  const m = (uuid: string, score: number, takenAt: string, over: Partial<P.MockRecord> = {}): P.MockRecord => ({
    uuid, examId: 3, examName: 'JAMB', score, total: 400, durationSeconds: 3000, takenAt, subjectScores: [{ subject_id: 1, correct: 10, total: 60 }], ...over,
  });

  it('are stored once and listed newest first', async () => {
    await P.saveMock(db, m('a', 200, '2026-09-20T10:00:00.000Z'));
    await P.saveMock(db, m('a', 999, '2026-09-20T10:00:00.000Z'));
    await P.saveMock(db, m('b', 250, '2026-09-25T10:00:00.000Z'));

    const list = await P.mocks(db);
    expect(list.map((x) => [x.uuid, x.score])).toEqual([['b', 250], ['a', 200]]);
    expect(JSON.parse(list[0].subject_scores)).toEqual([{ subject_id: 1, correct: 10, total: 60 }]);
  });

  it('points versus the previous mock for the same exam and total', async () => {
    await P.saveMock(db, m('a', 200, '2026-09-20T10:00:00.000Z'));
    await P.saveMock(db, m('b', 222, '2026-09-25T10:00:00.000Z'));
    await P.saveMock(db, m('w', 80, '2026-09-22T10:00:00.000Z', { total: 100 }));   // a different format is not comparable

    expect(await P.changeSincePrevious(db, { uuid: 'a', examId: 3, score: 200, total: 400, takenAt: '2026-09-20T10:00:00.000Z' })).toBeNull();
    expect(await P.changeSincePrevious(db, { uuid: 'b', examId: 3, score: 222, total: 400, takenAt: '2026-09-25T10:00:00.000Z' })).toBe(22);
  });

  it('unsynced mocks go up once', async () => {
    await P.saveMock(db, m('a', 200, '2026-09-20T10:00:00.000Z'));
    await P.saveMock(db, m('b', 250, '2026-09-25T10:00:00.000Z', { synced: true }));

    expect((await P.unsyncedMocks(db)).map((x) => x.uuid)).toEqual(['a']);
    await P.markMocksSynced(db, ['a']);
    expect(await P.unsyncedMocks(db)).toHaveLength(0);
  });
});

describe('key-value and clearing', () => {
  it('kv reads, overwrites and deletes', async () => {
    expect(await P.kvGet(db, 'x')).toBeNull();
    await P.kvSet(db, 'x', '1');
    await P.kvSet(db, 'x', '2');
    expect(await P.kvGet(db, 'x')).toBe('2');
    await P.kvDelete(db, 'x');
    expect(await P.kvGet(db, 'x')).toBeNull();
  });

  it('signing out clears the student\'s records but keeps settings and packs', async () => {
    await P.recordAttempt(db, attempt());
    await P.setBookmark(db, { questionId: 1, examId: 1, subjectId: 1, examSlug: 'a', subjectSlug: 'b', bookmarked: true, changedAt: '2026-09-26T10:00:00.000Z' });
    await P.saveMock(db, { uuid: 'm', examId: 1, examName: 'x', score: 1, total: 100, durationSeconds: 1, takenAt: '2026-09-26T10:00:00.000Z', subjectScores: [] });
    await P.kvSet(db, 'sync.cursor', '99');
    await P.kvSet(db, 'settings', '{"theme":"dark"}');
    await db.runAsync("INSERT INTO packs (exam_slug, subject_slug, version, sha256, size_bytes, question_count, paper_count, years, starter, installed_at) VALUES ('jamb','physics',1,NULL,10,1,1,'[2020]',0,'2026-09-26T10:00:00.000Z')");

    await P.clearStudentData(db);

    expect((await P.totals(db)).answered).toBe(0);
    expect((await P.bookmarkedIds(db)).size).toBe(0);
    expect(await P.mocks(db)).toHaveLength(0);
    expect(await P.kvGet(db, 'sync.cursor')).toBeNull();
    expect(await P.kvGet(db, 'settings')).toBe('{"theme":"dark"}');
    expect((await db.getFirstAsync<{ n: number }>('SELECT COUNT(*) AS n FROM packs'))?.n).toBe(1);
  });
});
