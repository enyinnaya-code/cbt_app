import { challengeSession, hashSeed, pickQuestions, subjectOrder } from '@/core/challenge';
import { qualifying, totalXp } from '@/core/engagement';
import { applyServerChallenge, challengeFor, localDay, recordAttempt, unsyncedChallenges, type AttemptInput } from '@/db/progress';
import { completeChallenge, todaysChallenge } from '@/services/challenge';
import { engagement } from '@/services/engagement';
import type { PackManager } from '@/services/packs';
import { simplePack } from './helpers/fixtures';
import { memoryDb } from './helpers/sqlite';

const ref = (exam: string, subject: string, n = 20) => ({ exam_slug: exam, subject_slug: subject, question_count: n, display_name: subject, subject_name: subject });

describe('choosing the day\'s subject and questions', () => {
  it('is stable for the day and different from one day to the next', () => {
    const packs = [ref('waec', 'biology'), ref('waec', 'chemistry'), ref('waec', 'physics')];

    expect(subjectOrder(packs, 'u1|2026-10-10')).toEqual(subjectOrder(packs, 'u1|2026-10-10'));
    const firsts = new Set(['2026-10-10', '2026-10-11', '2026-10-12', '2026-10-13', '2026-10-14'].map((d) => subjectOrder(packs, `u1|${d}`)[0].subject_slug));
    expect(firsts.size).toBeGreaterThan(1);
  });

  it('skips subjects with too few questions and prefers the target exam', () => {
    const packs = [ref('jamb', 'physics', 3), ref('jamb', 'biology'), ref('waec', 'chemistry')];

    expect(subjectOrder(packs, 'k').map((p) => p.subject_slug).sort()).toEqual(['biology', 'chemistry']);
    expect(subjectOrder(packs, 'k', 'waec').map((p) => p.subject_slug)).toEqual(['chemistry']);
    expect(subjectOrder([], 'k')).toEqual([]);
  });

  it('picks 10 of the same questions every time for the same seed, and none for a tiny pack', () => {
    const pack = simplePack('waec', 'physics', 30);
    const a = pickQuestions(pack, 'u1|2026-10-10');

    expect(a).toHaveLength(10);
    expect(pickQuestions(pack, 'u1|2026-10-10')).toEqual(a);
    expect(pickQuestions(pack, 'u1|2026-10-11')).not.toEqual(a);
    expect(pickQuestions(simplePack('waec', 'physics', 3), 'x')).toBeNull();
  });

  it('rebuilds a stored challenge in its original order', () => {
    const pack = simplePack('waec', 'physics', 30);
    const ids = pickQuestions(pack, 'seed') as number[];

    expect(challengeSession(pack, ids).questions.map((q) => q.id)).toEqual(ids);
  });

  it('hashes the same text to the same number', () => {
    expect(hashSeed('abc')).toBe(hashSeed('abc'));
    expect(hashSeed('abc')).not.toBe(hashSeed('abd'));
  });
});

describe('the challenge on the phone', () => {
  const now = new Date(2026, 9, 10, 12);
  const day = localDay(now.toISOString());
  const pack = simplePack('waec', 'physics', 30);

  const packs = {
    installed: async () => [{ exam_slug: 'waec', subject_slug: 'physics', exam_name: 'WAEC', subject_name: 'Physics', display_name: 'Physics', question_count: 30 }],
    load: async () => pack,
  } as unknown as PackManager;

  const answer = (db: Awaited<ReturnType<typeof memoryDb>>, id: number, correct: boolean, n: number) => {
    const a: AttemptInput = {
      uuid: `a${n}`, questionId: id, examId: 1, examSlug: 'waec', subjectId: 1, subjectSlug: 'physics', subjectName: 'Physics', year: 2020,
      topicId: null, topicName: null, mode: 'practice', selected: correct ? 'B' : 'A', isCorrect: correct, timeMs: 1000,
      answeredAt: new Date(2026, 9, 10, 9, n).toISOString(),
    };
    return recordAttempt(db, a);
  };

  it('is created once for the day and reopens with the same questions', async () => {
    const db = await memoryDb();
    const first = await todaysChallenge(db, packs, 'u1', null, now);
    const ids = (await challengeFor(db, day))?.question_ids;
    await todaysChallenge(db, packs, 'u1', null, now);

    expect(first).toMatchObject({ total: 10, answered: 0, done: false, subject: 'Physics' });
    expect((await challengeFor(db, day))?.question_ids).toBe(ids);
  });

  it('pays the bonus only when every question is answered, and only once', async () => {
    const db = await memoryDb();
    await todaysChallenge(db, packs, 'u1', null, now);
    const ids: number[] = JSON.parse((await challengeFor(db, day))!.question_ids);

    for (let i = 0; i < 6; i++) await answer(db, ids[i], true, i);
    expect(await completeChallenge(db, now)).toMatchObject({ done: false, xp: 0 });

    for (let i = 6; i < 10; i++) await answer(db, ids[i], i < 8, i);
    const done = await completeChallenge(db, now);
    const again = await completeChallenge(db, now);

    expect(done).toMatchObject({ done: true, newly: true, correct: 8, total: 10, xp: 100 });
    expect(again).toMatchObject({ done: true, newly: false });
    expect((await unsyncedChallenges(db)).map((c) => c.day)).toEqual([day]);
  });

  it('counts the first answer to each question', async () => {
    const db = await memoryDb();
    await todaysChallenge(db, packs, 'u1', null, now);
    const ids: number[] = JSON.parse((await challengeFor(db, day))!.question_ids);

    for (let i = 0; i < 10; i++) await answer(db, ids[i], false, i);
    await answer(db, ids[0], true, 30);                 // a retry later does not turn it into a right answer

    expect(await completeChallenge(db, now)).toMatchObject({ correct: 0, total: 10 });
  });

  it('the server\'s bonus replaces the phone\'s, so both devices show the same XP', async () => {
    const db = await memoryDb();
    await todaysChallenge(db, packs, 'u1', null, now);
    const ids: number[] = JSON.parse((await challengeFor(db, day))!.question_ids);
    for (let i = 0; i < 10; i++) await answer(db, ids[i], true, i);
    await completeChallenge(db, now);

    // The website had already paid this day, so the server says 0 extra... then 100 total, never 200.
    await applyServerChallenge(db, { day, correct: 9, total: 10, xp: 100 });

    const snap = await engagement(db, now);
    expect(snap.level.xp).toBe(10 * 10 + 100);
    expect(snap.badges.find((b) => b.key === 'first_challenge')?.earned).toBe(true);
  });

  it('a challenge done on the website shows as done on a new phone', async () => {
    const db = await memoryDb();
    await applyServerChallenge(db, { day, correct: 7, total: 10, xp: 100 });

    expect(await todaysChallenge(db, packs, 'u1', null, now)).toMatchObject({ done: true, correct: 7, total: 10, xp: 100 });
  });

  it('there is no challenge until a subject with enough questions is downloaded', async () => {
    const none = { installed: async () => [], load: async () => null } as unknown as PackManager;

    expect(await todaysChallenge(await memoryDb(), none, 'u1', null, now)).toBeNull();
  });
});

describe('challenge XP and badges', () => {
  it('adds the bonus to answer XP', () => {
    expect(totalXp([{ day: '2026-10-10', answered: 10, correct: 10 }], 200)).toBe(300);
  });

  it('awards the challenge badges and a perfect challenge counts as a perfect score', () => {
    const base = { answered: 0, correct: 0, days: [], mocks: [] };

    expect(qualifying({ ...base, challenges: [{ correct: 10, total: 10 }] })).toEqual(['first_challenge', 'perfect_score']);
    expect(qualifying({ ...base, challenges: Array.from({ length: 5 }, () => ({ correct: 5, total: 10 })) })).toEqual(['first_challenge', 'challenge_5']);
    expect(qualifying(base)).toEqual([]);
  });
});
