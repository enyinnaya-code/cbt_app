import { dayXp, levelFor, longestStreak, message, qualifying, totalXp } from '@/core/engagement';
import { recordAttempt, saveMock, type AttemptInput } from '@/db/progress';
import { engagement } from '@/services/engagement';
import { memoryDb } from './helpers/sqlite';

describe('XP and levels', () => {
  it('rewards correct answers and effort', () => {
    expect(dayXp({ answered: 2, correct: 1 })).toBe(12);
  });

  it('caps answer XP per day so repeating one question cannot farm levels', () => {
    expect(dayXp({ answered: 100, correct: 100 })).toBe(400);
    expect(totalXp([{ day: '2026-10-01', answered: 100, correct: 100 }, { day: '2026-10-02', answered: 1, correct: 1 }])).toBe(410);
  });

  it('works out the level, progress and distance to the next level', () => {
    expect(levelFor(0)).toMatchObject({ level: 1, title: 'Beginner', toNext: 100 });
    expect(levelFor(100).title).toBe('Learner');
    expect(levelFor(250).pct).toBe(50); // halfway from 100 to 400
    expect(levelFor(99999)).toMatchObject({ next: null, pct: 100, toNext: null });
  });
});

describe('streaks and badges', () => {
  it('finds the longest run of consecutive days', () => {
    expect(longestStreak(['2026-10-01', '2026-10-02', '2026-10-03', '2026-10-05', '2026-10-06'])).toBe(3);
    expect(longestStreak(['2026-10-01', '2026-10-01'])).toBe(1);
    expect(longestStreak([])).toBe(0);
  });

  it('awards badges from answers, streaks and mocks', () => {
    const days = ['01', '02', '03', '04', '05', '06', '07'].map((d) => `2026-10-${d}`);
    const got = qualifying({
      answered: 120, correct: 100, days,
      mocks: [
        { examId: 1, score: 50, total: 100, takenAt: '2026-10-01T10:00:00Z' },
        { examId: 1, score: 100, total: 100, takenAt: '2026-10-05T10:00:00Z' },
      ],
    });
    expect(got.sort()).toEqual(['century', 'comeback', 'first_mock', 'first_steps', 'perfect_score', 'streak_7'].sort());
  });

  it('awards nothing to a new student', () => {
    expect(qualifying({ answered: 0, correct: 0, days: [], mocks: [] })).toEqual([]);
  });

  it('a comeback is judged within one exam, not across exams', () => {
    const got = qualifying({ answered: 0, correct: 0, days: [], mocks: [
      { examId: 1, score: 40, total: 100, takenAt: '2026-10-01T10:00:00Z' },
      { examId: 2, score: 90, total: 100, takenAt: '2026-10-02T10:00:00Z' },
    ] });
    expect(got).not.toContain('comeback');
  });

  it('picks a warm message from what the student did', () => {
    expect(message(8, 0)).toMatch(/8 days in a row/);
    expect(message(3, 0)).toMatch(/3-day streak/);
    expect(message(0, 12)).toMatch(/Great session/);
    expect(message(0, 0)).toMatch(/short session/);
  });
});

describe('engagement snapshot from the phone database', () => {
  const attempt = (n: number, correct: boolean, at: string): AttemptInput => ({
    uuid: `u${n}-${at}`, questionId: n, examId: 1, examSlug: 'waec', subjectId: 1, subjectSlug: 'physics', subjectName: 'Physics', year: 2020,
    topicId: null, topicName: null, mode: 'practice', selected: correct ? 'B' : 'A', isCorrect: correct, timeMs: 1000, answeredAt: at,
  });

  it('computes XP and level, and remembers a badge once earned', async () => {
    const db = await memoryDb();
    const now = new Date(2026, 9, 10, 12);
    for (let i = 0; i < 10; i++) await recordAttempt(db, attempt(i, true, new Date(2026, 9, 10, 9, i).toISOString()));

    const first = await engagement(db, now);
    expect(first.level).toMatchObject({ xp: 100, level: 2, title: 'Learner' });
    expect(first.xpToday).toBe(100);
    expect(first.newBadges).toEqual(['first_steps']);
    expect(first.badges.find((b) => b.key === 'first_steps')?.earned).toBe(true);

    // Asking again does not announce the badge a second time.
    expect((await engagement(db, now)).newBadges).toEqual([]);
  });

  it('a perfect mock earns the mock badges', async () => {
    const db = await memoryDb();
    await saveMock(db, { uuid: 'm1', examId: 1, examName: 'WAEC', score: 50, total: 50, durationSeconds: 600, takenAt: new Date(2026, 9, 10, 10).toISOString(), subjectScores: [] });

    const snap = await engagement(db, new Date(2026, 9, 10, 12));

    expect(snap.newBadges.sort()).toEqual(['first_mock', 'perfect_score']);
  });
});
