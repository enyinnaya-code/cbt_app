/**
 * XP, levels and badges, worked out on the phone from the student's own answers. These are the same rules as the
 * website (config/testacbt.php and App\Services\Engagement), so a student sees the same level on both. Nothing here
 * is stored as a counter: it is recalculated from the attempts, so a synced answer can never be counted twice.
 *
 * The daily challenge bonus (100 XP, one challenge a day across all devices) comes from the challenges table, which the
 * server's answer overwrites, so the phone and the website agree.
 */
export const XP = { correct: 10, wrong: 2, dailyAnswerCap: 400 } as const;

export const LEVELS = [
  { xp: 0, title: 'Beginner' },
  { xp: 100, title: 'Learner' },
  { xp: 400, title: 'Rising Scholar' },
  { xp: 1000, title: 'Scholar' },
  { xp: 2000, title: 'Advanced Scholar' },
  { xp: 4000, title: 'Expert' },
  { xp: 8000, title: 'Master' },
] as const;

export interface DayTotals {
  day: string; // YYYY-MM-DD
  answered: number;
  correct: number;
}

/** Answer XP for one day: effort counts, and the daily cap stops one question being repeated for levels. */
export function dayXp(d: Pick<DayTotals, 'answered' | 'correct'>): number {
  return Math.min(d.correct * XP.correct + (d.answered - d.correct) * XP.wrong, XP.dailyAnswerCap);
}

/** Answer XP over all days, plus the bonus from each completed daily challenge. */
export function totalXp(days: DayTotals[], challengeBonus = 0): number {
  return days.reduce((sum, d) => sum + dayXp(d), 0) + challengeBonus;
}

export interface LevelInfo {
  level: number;
  title: string;
  xp: number;
  next: number | null;
  nextTitle: string | null;
  pct: number;
  toNext: number | null;
}

export function levelFor(xp: number): LevelInfo {
  let i = 0;
  LEVELS.forEach((l, n) => { if (xp >= l.xp) i = n; });
  const next = LEVELS[i + 1] ?? null;
  const floor = LEVELS[i].xp;

  return {
    level: i + 1,
    title: LEVELS[i].title,
    xp,
    next: next?.xp ?? null,
    nextTitle: next?.title ?? null,
    pct: next ? Math.floor(((xp - floor) / (next.xp - floor)) * 100) : 100,
    toNext: next ? next.xp - xp : null,
  };
}

/** The longest run of consecutive days with at least one answer. */
export function longestStreak(days: string[]): number {
  const sorted = [...new Set(days)].sort();
  let best = 0;
  let run = 0;
  let prev: number | null = null;

  for (const d of sorted) {
    const [y, m, dd] = d.split('-').map(Number);
    const t = Date.UTC(y, m - 1, dd);
    run = prev !== null && t - prev === 86_400_000 ? run + 1 : 1;
    best = Math.max(best, run);
    prev = t;
  }
  return best;
}

export type BadgeKey = 'first_steps' | 'century' | 'streak_7' | 'first_challenge' | 'challenge_5' | 'first_mock' | 'perfect_score' | 'comeback';

export const BADGES: Record<BadgeKey, { title: string; text: string; icon: 'play' | 'check' | 'flame' | 'book' | 'trophy' | 'chart' | 'clock' }> = {
  first_steps: { title: 'First steps', text: 'Answered your first 10 questions', icon: 'play' },
  century: { title: 'Century', text: '100 correct answers', icon: 'check' },
  streak_7: { title: '7-day streak', text: 'Practised seven days in a row', icon: 'flame' },
  first_challenge: { title: 'Challenger', text: 'Completed a daily challenge', icon: 'clock' },
  challenge_5: { title: 'Regular', text: 'Completed 5 daily challenges', icon: 'chart' },
  first_mock: { title: 'First mock', text: 'Finished your first mock exam', icon: 'book' },
  perfect_score: { title: 'Perfect score', text: '100% on a challenge or mock exam', icon: 'trophy' },
  comeback: { title: 'Comeback', text: 'Beat your previous mock score by 10 points', icon: 'chart' },
};

export interface MockResult {
  examId: number;
  score: number;
  total: number;
  takenAt: string;
}

export interface BadgeInput {
  answered: number;
  correct: number;
  days: string[];
  mocks: MockResult[];
  /** Completed daily challenges. */
  challenges?: { correct: number; total: number }[];
}

/** Every badge the student qualifies for right now. */
export function qualifying(input: BadgeInput): BadgeKey[] {
  const challenges = input.challenges ?? [];
  const mocks = input.mocks.filter((m) => m.total > 0).sort((a, b) => a.takenAt.localeCompare(b.takenAt));

  const byExam = new Map<number, number[]>();
  for (const m of mocks) byExam.set(m.examId, [...(byExam.get(m.examId) ?? []), (m.score / m.total) * 100]);
  const comeback = [...byExam.values()].some((p) => p.some((v, i) => i > 0 && v - p[i - 1] >= 10));

  const earned: [BadgeKey, boolean][] = [
    ['first_steps', input.answered >= 10],
    ['century', input.correct >= 100],
    ['streak_7', longestStreak(input.days) >= 7],
    ['first_challenge', challenges.length >= 1],
    ['challenge_5', challenges.length >= 5],
    ['first_mock', mocks.length > 0],
    ['perfect_score', mocks.some((m) => m.score >= m.total) || challenges.some((c) => c.total > 0 && c.correct >= c.total)],
    ['comeback', comeback],
  ];
  return earned.filter(([, yes]) => yes).map(([key]) => key);
}

/** One warm line for Home, chosen from what the student actually did. */
export function message(streak: number, answeredToday: number): string {
  if (streak >= 7) return `${streak} days in a row. That consistency is what passes exams.`;
  if (streak >= 2 && answeredToday === 0) return `Practise today to keep your ${streak}-day streak going.`;
  if (answeredToday >= 10) return 'Great session today. You are getting stronger.';
  if (answeredToday > 0) return 'Good start. A few more questions keeps the momentum going.';
  return 'A short session today beats a long one next week.';
}
