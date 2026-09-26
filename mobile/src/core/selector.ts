/**
 * Chooses questions from a downloaded pack for Practice, Saved and Mock. This is a port of the website's
 * QuestionSelector, so the app and the site follow the same rules:
 *  - only questions with a usable answer key are offered;
 *  - a passage stays attached to its questions and is never split up by shuffling;
 *  - an instruction followed by many questions is general directions, not a passage;
 *  - one chosen year keeps the paper's order, "all years" mixes whole passage groups.
 */
import type { Pack, PackMcq, SessionData, SessionQuestion } from './types';

/** An instruction followed by more questions than this is general directions, not a passage. */
export const PASSAGE_MAX_QUESTIONS = 10;

export type Rng = () => number;

/** Small seedable random number generator (mulberry32), so shuffles can be tested. */
export function seededRng(seed: number): Rng {
  let a = seed >>> 0;
  return () => {
    a = (a + 0x6d2b79f5) >>> 0;
    let t = a;
    t = Math.imul(t ^ (t >>> 15), t | 1);
    t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

export interface Candidate {
  q: PackMcq;
  year: number;
  passageId: number | null;
  paperId: number;
}

export function isUsable(q: PackMcq): boolean {
  const answer = (q.answer ?? '').trim().toUpperCase();
  const options = Object.values(q.options ?? {}).filter((o) => o !== null && o !== '');
  return answer !== '' && q.options?.[answer] !== undefined && q.options[answer] !== '' && options.length >= 2;
}

export interface CandidateFilter {
  year?: number | null;
  topicId?: number | null;
  onlyIds?: Set<number> | null;
}

/** Usable multiple-choice questions in paper order, each with the passage that introduces it (if any). */
export function candidates(pack: Pack, filter: CandidateFilter = {}): Candidate[] {
  const out: Candidate[] = [];

  for (const paper of pack.papers) {
    if (filter.year && paper.year !== filter.year) continue;

    // First pass: which instruction owns each question, and how many questions each instruction covers.
    const owner = new Map<number, number>();
    const size = new Map<number, number>();
    let current: number | null = null;
    for (const item of paper.items) {
      if (item.type === 'instruction') { current = item.id; continue; }
      if (current !== null) {
        owner.set(item.id, current);
        size.set(current, (size.get(current) ?? 0) + 1);
      }
    }

    for (const item of paper.items) {
      if (item.type !== 'mcq' || !isUsable(item)) continue;
      if (filter.topicId && item.topic_id !== filter.topicId) continue;
      if (filter.onlyIds && !filter.onlyIds.has(item.id)) continue;

      const instruction = owner.get(item.id) ?? null;
      const passageId = instruction !== null && (size.get(instruction) ?? 0) <= PASSAGE_MAX_QUESTIONS ? instruction : null;
      out.push({ q: item, year: paper.year, passageId, paperId: paper.id });
    }
  }

  return out;
}

function shuffle<T>(items: T[], rng: Rng): T[] {
  const a = items.slice();
  for (let i = a.length - 1; i > 0; i--) {
    const j = Math.floor(rng() * (i + 1));
    [a[i], a[j]] = [a[j], a[i]];
  }
  return a;
}

/** Keeps order when asked; otherwise shuffles whole passage groups (a lone question is its own group). */
export function choose(cands: Candidate[], count: number, keepOrder: boolean, rng: Rng): Candidate[] {
  let ordered = cands;

  if (!keepOrder) {
    const groups = new Map<string, Candidate[]>();
    for (const c of cands) {
      const key = c.passageId !== null ? `p${c.paperId}-${c.passageId}` : `q${c.q.id}`;
      const g = groups.get(key);
      g ? g.push(c) : groups.set(key, [c]);
    }
    ordered = shuffle([...groups.values()], rng).flat();
  }

  return ordered.slice(0, Math.max(1, count));
}

/** Turns chosen candidates into what the session screens need, with each passage's text included once. */
export function toSession(pack: Pack, chosen: Candidate[]): SessionData {
  const passageText = new Map<number, string>();
  for (const paper of pack.papers) for (const item of paper.items) if (item.type === 'instruction') passageText.set(item.id, item.html);

  const topicName = new Map(pack.topics.map((t) => [t.id, t.name]));
  const passages: Record<number, string> = {};

  const questions: SessionQuestion[] = chosen.map(({ q, year, passageId }) => {
    if (passageId !== null && passageText.has(passageId)) passages[passageId] = passageText.get(passageId) as string;

    return {
      id: q.id,
      html: q.html,
      options: Object.fromEntries(Object.entries(q.options).filter(([, v]) => v !== null && v !== '')),
      answer: q.answer.trim().toUpperCase(),
      marks: q.marks || 1,
      topicId: q.topic_id,
      topic: q.topic_id !== null ? topicName.get(q.topic_id) ?? null : null,
      year,
      passageId: passageId !== null && passageText.has(passageId) ? passageId : null,
      explanationEn: q.explanation_en,
      explanationPcm: q.explanation_pcm,
      examSlug: pack.exam.slug,
      subjectSlug: pack.subject.slug,
    };
  });

  return { questions, passages };
}

/** Practice from one pack: a year (keeps paper order) or all years (mixed), optionally one topic. */
export function practiceSession(pack: Pack, opts: { year?: number | null; topicId?: number | null; count: number; rng?: Rng }): SessionData {
  const cands = candidates(pack, { year: opts.year, topicId: opts.topicId });
  return toSession(pack, choose(cands, opts.count, !!opts.year, opts.rng ?? Math.random));
}

/** Questions the student bookmarked, drawn from whichever packs hold them. */
export function savedSession(packs: Pack[], ids: Set<number>, count: number, rng: Rng = Math.random): SessionData {
  const merged: SessionData = { questions: [], passages: {} };

  for (const pack of packs) {
    const chosen = choose(candidates(pack, { onlyIds: ids }), Number.MAX_SAFE_INTEGER, false, rng);
    const s = toSession(pack, chosen);
    merged.questions.push(...s.questions);
    Object.assign(merged.passages, s.passages);
  }

  merged.questions = shuffle(merged.questions, rng).slice(0, Math.max(1, count));
  return merged;
}

/** Usable question count and the years available, so the setup screen can show what is on the phone. */
export function summarise(pack: Pack): { questions: number; years: number[] } {
  const c = candidates(pack);
  return { questions: c.length, years: [...new Set(c.map((x) => x.year))].sort((a, b) => b - a) };
}
