/**
 * Mock exam building and marking, on the phone. It follows the server's rules (MockService), so a mock
 * taken offline scores exactly as it would on the website:
 *  - subjects in the exam's order (compulsory first, then A to Z);
 *  - each subject uses the real exam's question count, or all it has, and the clock shrinks in proportion;
 *  - each subject is marked out of 100, so JAMB's four subjects total 400.
 */
import { choose, candidates, seededRng, toSession, type Rng } from './selector';
import type { MockFormat, Pack, SessionData, SessionQuestion } from './types';

export const MIN_QUESTIONS = 5;

export interface MockGroup {
  subjectSlug: string;
  subjectId: number;
  name: string;
  count: number;
}

export interface MockPaper extends SessionData {
  groups: MockGroup[];
  minutes: number;
}

export interface SubjectPack {
  subjectId: number;
  pack: Pack;
}

export function plannedQuestions(format: MockFormat, subjectSlug: string): number {
  return format.questions[subjectSlug] ?? format.questions.default;
}

export function displayName(pack: Pack): string {
  return pack.subject.display_name || pack.subject.name;
}

/** Whether a set of chosen subjects fits the exam's format, with a message for the student when it does not. */
export function validateChoice(format: MockFormat, slugs: string[]): string | null {
  const unique = [...new Set(slugs)];
  if (unique.length !== format.subject_count) {
    return format.subject_count === 1 ? 'Choose one subject.' : `Choose exactly ${format.subject_count} subjects.`;
  }
  if (format.compulsory && !unique.includes(format.compulsory)) return 'Use of English is compulsory for this exam.';
  return null;
}

export function buildMock(format: MockFormat, chosen: SubjectPack[], rng: Rng = Math.random): MockPaper {
  const ordered = chosen.slice().sort((a, b) => {
    const ac = a.pack.subject.slug === format.compulsory ? 0 : 1;
    const bc = b.pack.subject.slug === format.compulsory ? 0 : 1;
    return ac - bc || displayName(a.pack).localeCompare(displayName(b.pack));
  });

  const questions: SessionQuestion[] = [];
  const passages: Record<number, string> = {};
  const groups: MockGroup[] = [];
  let planned = 0;

  for (const { subjectId, pack } of ordered) {
    const want = plannedQuestions(format, pack.subject.slug);
    const picked = choose(candidates(pack), want, false, rng);
    const session = toSession(pack, picked);

    planned += want;
    questions.push(...session.questions);
    Object.assign(passages, session.passages);
    groups.push({ subjectSlug: pack.subject.slug, subjectId, name: displayName(pack), count: session.questions.length });
  }

  // Fewer questions than the real exam: shorten the clock in proportion, never below five minutes.
  const minutes = questions.length < planned ? Math.max(5, Math.ceil((format.minutes * questions.length) / planned)) : format.minutes;

  return { questions, passages, groups, minutes };
}

export interface GroupScore {
  subjectId: number;
  name: string;
  correct: number;
  answered: number;
  total: number;
  percent: number;
}

export interface MockScore {
  groups: GroupScore[];
  score: number;
  total: number;
  correct: number;
  answered: number;
  questions: number;
  topics: { topicId: number; name: string; correct: number; total: number }[];
}

/** answers: question id to letter. Marks each subject out of 100 and adds them up. */
export function scoreMock(paper: Pick<MockPaper, 'questions' | 'groups'>, answers: Record<number, string>): MockScore {
  let offset = 0;
  const groups: GroupScore[] = [];
  const topics = new Map<number, { topicId: number; name: string; correct: number; total: number }>();

  for (const g of paper.groups) {
    const slice = paper.questions.slice(offset, offset + g.count);
    offset += g.count;

    let correct = 0;
    let answered = 0;
    for (const q of slice) {
      const picked = answers[q.id] ?? null;
      const right = picked !== null && picked.toUpperCase() === q.answer;
      if (picked !== null) answered++;
      if (right) correct++;

      if (q.topicId !== null && q.topic) {
        const t = topics.get(q.topicId) ?? { topicId: q.topicId, name: q.topic, correct: 0, total: 0 };
        t.total++;
        if (right) t.correct++;
        topics.set(q.topicId, t);
      }
    }

    groups.push({
      subjectId: g.subjectId, name: g.name, correct, answered, total: slice.length,
      percent: slice.length ? Math.round((correct / slice.length) * 100) : 0,
    });
  }

  return {
    groups,
    score: groups.reduce((s, g) => s + g.percent, 0),
    total: 100 * groups.length,
    correct: groups.reduce((s, g) => s + g.correct, 0),
    answered: groups.reduce((s, g) => s + g.answered, 0),
    questions: paper.questions.length,
    topics: [...topics.values()],
  };
}

export { seededRng };
