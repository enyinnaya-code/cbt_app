/**
 * Turns "the student answered this" into rows on the phone. Used by Practice and by the mock exam.
 */
import { recordAttempt, setBookmark, type AttemptInput } from '@/db/progress';
import type { Db } from '@/db/types';
import type { SessionQuestion } from '@/core/types';
import type { CatalogIndex } from './catalog';

export async function recordAnswer(
  db: Db,
  index: CatalogIndex,
  q: SessionQuestion,
  selected: string | null,
  mode: 'practice' | 'mock',
  timeMs: number | null,
  uuid: string,
  now: Date = new Date(),
): Promise<void> {
  const where = index.locate(q.examSlug, q.subjectSlug);

  const attempt: AttemptInput = {
    uuid, questionId: q.id, examId: where.examId, examSlug: q.examSlug, subjectId: where.subjectId, subjectSlug: q.subjectSlug,
    subjectName: where.subjectName, year: q.year, topicId: q.topicId, topicName: q.topic, mode, selected,
    isCorrect: selected !== null && selected.toUpperCase() === q.answer, timeMs, answeredAt: now.toISOString(),
  };

  await recordAttempt(db, attempt);
}

export async function toggleBookmark(db: Db, index: CatalogIndex, q: SessionQuestion, on: boolean, now: Date = new Date()): Promise<void> {
  const where = index.locate(q.examSlug, q.subjectSlug);

  // Newest change wins, so a tap made just now always beats what was synced before.
  await setBookmark(db, {
    questionId: q.id, examId: where.examId, subjectId: where.subjectId, examSlug: q.examSlug, subjectSlug: q.subjectSlug,
    bookmarked: on, changedAt: now.toISOString(),
  });
}
