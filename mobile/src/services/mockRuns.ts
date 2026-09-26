/**
 * A mock exam in progress lives in the phone's database, so closing the app, switching to another app or
 * running out of battery never loses the paper, the answers or the clock (the deadline is a fixed moment).
 */
import { buildMock, scoreMock, type MockGroup, type MockScore, type SubjectPack } from '@/core/mock';
import type { MockFormat, SessionQuestion } from '@/core/types';
import { changeSincePrevious, saveMock } from '@/db/progress';
import type { Db } from '@/db/types';
import type { Rng } from '@/core/selector';
import type { CatalogIndex } from './catalog';
import { recordAnswer } from './recorder';

export interface MockRun {
  uuid: string;
  examSlug: string;
  examId: number;
  examName: string;
  label: string;
  startedAt: string;
  deadlineAt: string;
  minutes: number;
  groups: MockGroup[];
  questions: SessionQuestion[];
  passages: Record<number, string>;
}

export interface MockProgress {
  answers: Record<number, string>;
  flagged: number[];
  position: number;
}

export interface MockResult extends MockScore {
  uuid: string;
  examName: string;
  label: string;
  durationSeconds: number;
  takenAt: string;
  change: number | null;
}

export async function startMock(
  db: Db,
  o: { uuid: string; examSlug: string; examId: number; examName: string; format: MockFormat; chosen: SubjectPack[]; rng?: Rng; now?: Date },
): Promise<MockRun> {
  const now = o.now ?? new Date();
  const paper = buildMock(o.format, o.chosen, o.rng);

  const run: MockRun = {
    uuid: o.uuid, examSlug: o.examSlug, examId: o.examId, examName: o.examName, label: o.format.label,
    startedAt: now.toISOString(), deadlineAt: new Date(now.getTime() + paper.minutes * 60000).toISOString(),
    minutes: paper.minutes, groups: paper.groups, questions: paper.questions, passages: paper.passages,
  };

  await db.runAsync(
    'INSERT INTO mock_runs (uuid, state, progress, finished, updated_at) VALUES (?,?,?,0,?)',
    [run.uuid, JSON.stringify(run), JSON.stringify({ answers: {}, flagged: [], position: 0 } satisfies MockProgress), now.toISOString()],
  );
  return run;
}

export async function activeMock(db: Db): Promise<{ run: MockRun; progress: MockProgress } | null> {
  const row = await db.getFirstAsync<{ state: string; progress: string }>('SELECT state, progress FROM mock_runs WHERE finished = 0 ORDER BY updated_at DESC LIMIT 1');
  if (!row) return null;
  return { run: JSON.parse(row.state) as MockRun, progress: { answers: {}, flagged: [], position: 0, ...JSON.parse(row.progress) } };
}

export async function saveProgress(db: Db, uuid: string, progress: MockProgress, now: Date = new Date()): Promise<void> {
  await db.runAsync('UPDATE mock_runs SET progress = ?, updated_at = ? WHERE uuid = ? AND finished = 0', [JSON.stringify(progress), now.toISOString(), uuid]);
}

/** Marks the paper, files every answer under the right subject and topic, and stores the result. Safe to call twice. */
export async function finishMock(
  db: Db,
  index: CatalogIndex,
  run: MockRun,
  answers: Record<number, string>,
  newUuid: () => string,
  now: Date = new Date(),
): Promise<MockResult> {
  const score = scoreMock(run, answers);
  const took = Math.min(run.minutes * 60, Math.max(0, Math.round((now.getTime() - new Date(run.startedAt).getTime()) / 1000)));

  const done = await db.getFirstAsync<{ finished: number }>('SELECT finished FROM mock_runs WHERE uuid = ?', [run.uuid]);

  if (done?.finished !== 1) {
    await db.withTransactionAsync(async () => {
      for (const q of run.questions) {
        const picked = answers[q.id];
        if (picked) await recordAnswer(db, index, q, picked, 'mock', null, newUuid(), now);
      }

      await saveMock(db, {
        uuid: run.uuid, examId: run.examId, examName: run.examName, score: score.score, total: score.total, durationSeconds: took,
        takenAt: now.toISOString(),
        subjectScores: score.groups.map((g) => ({ subject_id: g.subjectId, correct: g.correct, total: g.total })),
      });
      await db.runAsync('UPDATE mock_runs SET finished = 1, progress = ?, updated_at = ? WHERE uuid = ?', [JSON.stringify({ answers, flagged: [], position: 0 }), now.toISOString(), run.uuid]);
    });
  }

  const takenAt = (await db.getFirstAsync<{ taken_at: string }>('SELECT taken_at FROM mocks WHERE uuid = ?', [run.uuid]))?.taken_at ?? now.toISOString();
  const change = await changeSincePrevious(db, { uuid: run.uuid, examId: run.examId, score: score.score, total: score.total, takenAt });

  return { ...score, uuid: run.uuid, examName: run.examName, label: run.label, durationSeconds: took, takenAt, change };
}

/** Reopens a finished exam: the paper, the student's answers, and the marked result. */
export async function finishedMock(db: Db, uuid: string): Promise<{ run: MockRun; answers: Record<number, string>; result: MockResult } | null> {
  const row = await db.getFirstAsync<{ state: string; progress: string; finished: number }>('SELECT state, progress, finished FROM mock_runs WHERE uuid = ?', [uuid]);
  if (!row || row.finished !== 1) return null;

  const run = JSON.parse(row.state) as MockRun;
  const answers = (JSON.parse(row.progress) as { answers?: Record<number, string> }).answers ?? {};
  const score = scoreMock(run, answers);

  const mock = await db.getFirstAsync<{ taken_at: string; duration_seconds: number }>('SELECT taken_at, duration_seconds FROM mocks WHERE uuid = ?', [uuid]);
  const takenAt = mock?.taken_at ?? new Date().toISOString();
  const change = await changeSincePrevious(db, { uuid, examId: run.examId, score: score.score, total: score.total, takenAt });

  return { run, answers, result: { ...score, uuid, examName: run.examName, label: run.label, durationSeconds: mock?.duration_seconds ?? 0, takenAt, change } };
}

/** Whether an unfinished exam has run past its deadline (it should be marked automatically). */
export function isExpired(run: MockRun, now: Date = new Date()): boolean {
  return now.getTime() >= new Date(run.deadlineAt).getTime();
}
