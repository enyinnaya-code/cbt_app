import { practiceSession, seededRng } from '@/core/selector';
import * as P from '@/db/progress';
import type { Db } from '@/db/types';
import { CatalogIndex } from '@/services/catalog';
import { activeMock, finishMock, isExpired, saveProgress, startMock, type MockRun } from '@/services/mockRuns';
import { recordAnswer, toggleBookmark } from '@/services/recorder';
import { JAMB_FORMAT, WAEC_FORMAT, catalogOf, makePack, mcq, simplePack, subject } from './helpers/fixtures';
import { memoryDb } from './helpers/sqlite';

const index = new CatalogIndex(catalogOf([
  { id: 3, slug: 'jamb', name: 'JAMB', subjects: [subject(1, 'english-language', { display_name: 'Use of English' }), subject(2, 'chemistry'), subject(3, 'mathematics'), subject(4, 'physics')] },
  { id: 1, slug: 'waec', name: 'WAEC', subjects: [subject(4, 'physics')] },
]));

let db: Db;
let counter = 0;
const uuid = () => `id-${++counter}`;
beforeEach(async () => { db = await memoryDb(); counter = 0; });

const jambPacks = (n = 20) => [
  { subjectId: 1, pack: simplePack('jamb', 'english-language', n, 1000, 'Use of English') },
  { subjectId: 2, pack: simplePack('jamb', 'chemistry', n, 2000) },
  { subjectId: 3, pack: simplePack('jamb', 'mathematics', n, 3000) },
  { subjectId: 4, pack: simplePack('jamb', 'physics', n, 4000) },
];

const start = (over: Partial<Parameters<typeof startMock>[1]> = {}) =>
  startMock(db, { uuid: 'run-1', examSlug: 'jamb', examId: 3, examName: 'JAMB', format: JAMB_FORMAT, chosen: jambPacks(), rng: seededRng(1), now: new Date('2026-09-26T10:00:00Z'), ...over });

describe('startMock and resuming', () => {
  it('saves the paper and a fixed deadline', async () => {
    const run = await start();

    expect(run.questions).toHaveLength(80);
    expect(run.groups.map((g) => g.count)).toEqual([20, 20, 20, 20]);
    expect(run.minutes).toBe(Math.ceil((120 * 80) / 180));
    expect(new Date(run.deadlineAt).getTime() - new Date(run.startedAt).getTime()).toBe(run.minutes * 60000);
  });

  it('comes back exactly as it was left after the app is closed', async () => {
    const run = await start();
    const q = run.questions[3];
    await saveProgress(db, run.uuid, { answers: { [q.id]: 'A' }, flagged: [q.id], position: 3 });

    const back = await activeMock(db);

    expect(back?.run.uuid).toBe('run-1');
    expect(back?.run.questions.map((x) => x.id)).toEqual(run.questions.map((x) => x.id));
    expect(back?.run.deadlineAt).toBe(run.deadlineAt);
    expect(back?.progress).toEqual({ answers: { [q.id]: 'A' }, flagged: [q.id], position: 3 });
  });

  it('has nothing to resume when there is no exam in progress', async () => {
    expect(await activeMock(db)).toBeNull();
  });

  it('knows when the deadline has passed', async () => {
    const run = await start();
    expect(isExpired(run, new Date('2026-09-26T10:10:00Z'))).toBe(false);
    expect(isExpired(run, new Date(new Date(run.deadlineAt).getTime() + 1))).toBe(true);
  });
});

describe('finishMock', () => {
  const finishAfter = async (run: MockRun, minutes: number, answers: Record<number, string>) =>
    finishMock(db, index, run, answers, uuid, new Date(new Date(run.startedAt).getTime() + minutes * 60000));

  it('marks the exam, files every answer under its subject, and stores the result', async () => {
    const run = await start();
    const answers: Record<number, string> = {};
    run.questions.slice(0, 20).forEach((q) => (answers[q.id] = q.answer));      // all English right
    answers[run.questions[20].id] = 'A';                                          // one wrong in chemistry

    const r = await finishAfter(run, 12, answers);

    expect(r.groups.map((g) => g.percent)).toEqual([100, 0, 0, 0]);
    expect(r.score).toBe(100);
    expect(r.total).toBe(400);
    expect(r.answered).toBe(21);
    expect(r.durationSeconds).toBe(720);
    expect(await P.totals(db)).toEqual({ answered: 21, correct: 20 });

    const subjects = await P.subjectStats(db);
    expect(subjects.map((s) => [s.subject_slug, s.answered]).sort()).toEqual([['chemistry', 1], ['english-language', 20]]);
    expect((await P.mocks(db))[0]).toMatchObject({ uuid: 'run-1', score: 100, total: 400, exam_name: 'JAMB' });
    expect(await activeMock(db)).toBeNull();
  });

  it('answers are queued for sync as mock answers, and the result too', async () => {
    const run = await start();
    await finishAfter(run, 5, { [run.questions[0].id]: run.questions[0].answer });

    expect(await P.unsyncedAttempts(db)).toMatchObject([{ mode: 'mock', selected: run.questions[0].answer }]);
    expect((await P.unsyncedMocks(db)).map((m) => m.uuid)).toEqual(['run-1']);
  });

  it('is safe to call twice: nothing is recorded again', async () => {
    const run = await start();
    const answers = { [run.questions[0].id]: run.questions[0].answer };

    await finishAfter(run, 5, answers);
    await finishAfter(run, 6, answers);

    expect((await P.totals(db)).answered).toBe(1);
    expect(await P.mocks(db)).toHaveLength(1);
  });

  it('caps the time used at the time allowed', async () => {
    const run = await start();
    expect((await finishAfter(run, 600, {})).durationSeconds).toBe(run.minutes * 60);
  });

  it('reports points versus the previous mock for the same exam', async () => {
    const first = await start({ uuid: 'first' });
    await finishAfter(first, 10, Object.fromEntries(first.questions.slice(0, 20).map((q) => [q.id, q.answer])));

    const second = await start({ uuid: 'second', now: new Date('2026-09-27T10:00:00Z'), rng: seededRng(2) });
    const r = await finishMock(db, index, second, Object.fromEntries(second.questions.slice(0, 40).map((q) => [q.id, q.answer])), uuid, new Date('2026-09-27T10:20:00Z'));

    expect(r.score).toBe(200);
    expect(r.change).toBe(100);
  });

  it('a first mock has nothing to compare with', async () => {
    const run = await start();
    expect((await finishAfter(run, 3, {})).change).toBeNull();
  });

  it('a mock with no answers still scores zero and is recorded', async () => {
    const run = await start();
    const r = await finishAfter(run, 1, {});
    expect(r).toMatchObject({ score: 0, answered: 0 });
    expect(await P.mocks(db)).toHaveLength(1);
  });
});

describe('WAEC single subject', () => {
  it('scores out of 100', async () => {
    const run = await startMock(db, { uuid: 'w', examSlug: 'waec', examId: 1, examName: 'WAEC', format: WAEC_FORMAT, chosen: [{ subjectId: 4, pack: simplePack('waec', 'physics', 20) }], rng: seededRng(1), now: new Date('2026-09-26T10:00:00Z') });
    const r = await finishMock(db, index, run, Object.fromEntries(run.questions.slice(0, 10).map((q) => [q.id, q.answer])), uuid, new Date('2026-09-26T10:05:00Z'));

    expect([r.score, r.total]).toEqual([50, 100]);
  });
});

describe('recorder', () => {
  const pack = makePack({ exam: 'jamb', subject: 'physics', topics: [{ id: 7, name: 'Heat' }], papers: [{ id: 1, year: 2020, items: [mcq(1, { topic_id: 7 })] }] });

  it('files an answer under the right exam, subject, year and topic and judges it by the key', async () => {
    const s = practiceSession(pack, { year: 2020, count: 1 }).questions[0];

    await recordAnswer(db, index, s, 'B', 'practice', 3000, 'u1', new Date('2026-09-26T10:00:00Z'));
    await recordAnswer(db, index, s, 'A', 'practice', 2000, 'u2', new Date('2026-09-26T10:01:00Z'));
    await recordAnswer(db, index, s, null, 'practice', 1000, 'u3', new Date('2026-09-26T10:02:00Z'));

    const rows = await db.getAllAsync<Record<string, unknown>>('SELECT * FROM attempts ORDER BY uuid');
    expect(rows.map((r) => [r.selected, r.is_correct])).toEqual([['B', 1], ['A', 0], [null, 0]]);
    expect(rows[0]).toMatchObject({ exam_id: 3, exam_slug: 'jamb', subject_id: 4, subject_slug: 'physics', subject_name: 'physics', year: 2020, topic_id: 7, topic_name: 'Heat', synced: 0 });
  });

  it('bookmarking is newest-wins', async () => {
    const s = practiceSession(pack, { year: 2020, count: 1 }).questions[0];

    await toggleBookmark(db, index, s, true, new Date('2026-09-26T10:00:00Z'));
    await toggleBookmark(db, index, s, false, new Date('2026-09-26T10:00:05Z'));
    expect((await P.bookmarkedIds(db)).size).toBe(0);

    await toggleBookmark(db, index, s, true, new Date('2026-09-26T10:00:09Z'));
    expect([...(await P.bookmarkedIds(db))]).toEqual([1]);
  });
});
