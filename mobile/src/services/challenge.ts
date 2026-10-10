import { CHALLENGE, challengeSession, pickQuestions, subjectOrder } from '@/core/challenge';
import type { SessionData } from '@/core/types';
import { challengeFor, challengeProgress, createChallenge, finishChallenge, localDay, type ChallengeRow } from '@/db/progress';
import type { Db } from '@/db/types';
import type { PackManager } from './packs';

export interface TodaysChallenge {
  day: string;
  subject: string;
  total: number;
  answered: number;
  correct: number;
  done: boolean;
  xp: number;
}

const view = (row: ChallengeRow, answered: number, correct: number): TodaysChallenge => ({
  day: row.day, subject: row.subject_name ?? '', total: row.total, answered: row.completed ? row.total : answered,
  correct: row.completed ? row.correct ?? correct : correct, done: row.completed === 1, xp: row.xp,
});

/**
 * Today's challenge, created the first time it is asked for (offline is fine). Null when no downloaded subject has
 * enough questions yet. A challenge that arrived from the server (done on the website) has no questions here: it is shown
 * as done, and there is nothing more to play today.
 */
export async function todaysChallenge(db: Db, packs: PackManager, userKey: string, targetExam?: string | null, now: Date = new Date()): Promise<TodaysChallenge | null> {
  const day = localDay(now.toISOString());
  let row = await challengeFor(db, day);

  if (!row) {
    const seedKey = `${userKey}|${day}`;
    const metas = subjectOrder(await packs.installed(), seedKey, targetExam);

    for (const meta of metas) {
      const pack = await packs.load(meta.exam_slug, meta.subject_slug);
      const ids = pack ? pickQuestions(pack, seedKey) : null;
      if (!pack || !ids) continue;
      await createChallenge(db, { day, examSlug: meta.exam_slug, subjectSlug: meta.subject_slug, subjectName: meta.display_name || meta.subject_name, ids });
      break;
    }
    row = await challengeFor(db, day);
  }
  if (!row) return null;

  const progress = row.completed ? { answered: row.total, correct: row.correct ?? 0 } : await challengeProgress(db, day, JSON.parse(row.question_ids));
  return view(row, progress.answered, progress.correct);
}

/** The questions to play, or null when today's challenge came from the server and has none. */
export async function challengePlay(db: Db, packs: PackManager, now: Date = new Date()): Promise<SessionData | null> {
  const row = await challengeFor(db, localDay(now.toISOString()));
  const ids: number[] = row ? JSON.parse(row.question_ids) : [];
  if (!row || !ids.length || !row.exam_slug || !row.subject_slug) return null;

  const pack = await packs.load(row.exam_slug, row.subject_slug);
  const session = pack ? challengeSession(pack, ids) : null;
  return session?.questions.length ? session : null;
}

/**
 * Called when the student finishes the runner. Marks the challenge done once every question has an answer and counts the
 * bonus. Returns whether this call paid the bonus.
 */
export async function completeChallenge(db: Db, now: Date = new Date()): Promise<{ done: boolean; newly: boolean; correct: number; total: number; xp: number } | null> {
  const day = localDay(now.toISOString());
  const row = await challengeFor(db, day);
  if (!row) return null;

  if (row.completed) return { done: true, newly: false, correct: row.correct ?? 0, total: row.total, xp: row.xp };

  const { answered, correct } = await challengeProgress(db, day, JSON.parse(row.question_ids));
  if (answered < row.total) return { done: false, newly: false, correct, total: row.total, xp: 0 };

  const newly = await finishChallenge(db, day, correct, CHALLENGE.bonus);
  return { done: true, newly, correct, total: row.total, xp: newly ? CHALLENGE.bonus : row.xp };
}
