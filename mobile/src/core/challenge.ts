/**
 * The daily challenge on the phone: a fixed set of questions from one downloaded subject, a different subject most days.
 * The phone builds it itself so it works offline. One challenge a day pays the XP bonus across all devices: the server
 * keeps the first one finished and tells the phone what it paid.
 */
import { candidates, choose, seededRng, toSession } from './selector';
import type { Pack, SessionData } from './types';

export const CHALLENGE = { size: 10, min: 5, bonus: 100 } as const;

/** A small stable hash, so the same student gets the same subject and questions for the whole day. */
export function hashSeed(text: string): number {
  let h = 5381;
  for (let i = 0; i < text.length; i++) h = ((h << 5) + h + text.charCodeAt(i)) | 0;
  return h >>> 0;
}

export interface PackRef {
  exam_slug: string;
  subject_slug: string;
  question_count: number;
}

/**
 * Which downloaded subjects could host today's challenge, in the order to try them: starting from the one the day
 * picks and moving on, with the student's target exam first.
 */
export function subjectOrder<T extends PackRef>(packs: T[], seedKey: string, targetExam?: string | null): T[] {
  const usable = packs.filter((p) => p.question_count >= CHALLENGE.min).sort((a, b) => (a.exam_slug + a.subject_slug).localeCompare(b.exam_slug + b.subject_slug));
  const preferred = targetExam ? usable.filter((p) => p.exam_slug === targetExam) : [];
  const list = preferred.length ? preferred : usable;
  if (!list.length) return [];

  const start = hashSeed(seedKey) % list.length;
  return [...list.slice(start), ...list.slice(0, start)];
}

/** Picks the day's questions from one pack, or null when it has too few usable ones. */
export function pickQuestions(pack: Pack, seedKey: string): number[] | null {
  const cands = candidates(pack);
  if (cands.length < CHALLENGE.min) return null;
  return choose(cands, CHALLENGE.size, false, seededRng(hashSeed(seedKey))).map((c) => c.q.id);
}

/** Rebuilds a stored challenge in the order it was first shown. */
export function challengeSession(pack: Pack, ids: number[]): SessionData {
  const byId = new Map(candidates(pack, { onlyIds: new Set(ids) }).map((c) => [c.q.id, c]));
  return toSession(pack, ids.map((id) => byId.get(id)).filter((c): c is NonNullable<typeof c> => !!c));
}
