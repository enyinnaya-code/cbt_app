/**
 * Keeps the phone and the server in step. Everything the student does is saved on the phone first, then
 * uploaded here whenever there is a connection. Uploads are safe to repeat, so a dropped connection can
 * never double count an answer. On a new phone the same code downloads the student's history.
 */
import {
  bookmarkedIds, markAttemptsSynced, markBookmarksSynced, markMocksSynced, kvGet, kvSet, recordAttempt, saveMock,
  setBookmark, unsyncedAttempts, unsyncedBookmarks, unsyncedMocks,
} from '@/db/progress';
import type { Db } from '@/db/types';
import { Api, ApiError } from './api';
import type { CatalogIndex } from './catalog';

export interface SyncResult {
  pushed: { attempts: number; bookmarks: number; mocks: number };
  pulled: { attempts: number; bookmarks: number; mocks: number };
}

interface PullAttempt {
  id: number; client_uuid: string; question_id: number; mode: 'practice' | 'mock'; selected: string | null;
  is_correct: boolean; time_ms: number | null; answered_at: string;
  exam_id: number | null; subject_id: number | null; year: number | null; topic_id: number | null;
}

interface PullResponse {
  attempts: PullAttempt[];
  has_more: boolean;
  next_cursor: number;
  bookmarks: { question_id: number; bookmarked: boolean; changed_at: string; exam_id: number | null; subject_id: number | null }[];
  mock_sessions: { client_uuid: string; exam_id: number; subject_scores: { subject_id: number; correct: number; total: number }[]; score: number; total: number; duration_seconds: number; taken_at: string }[];
  server_time: string;
}

const CURSOR = 'sync.cursor';
const SINCE = 'sync.since';

export class SyncEngine {
  private running: Promise<SyncResult> | null = null;

  constructor(private readonly db: Db, private readonly api: Api, private readonly index: () => CatalogIndex) {}

  /** Only one sync runs at a time; callers that arrive meanwhile share its result. */
  run(): Promise<SyncResult> {
    if (!this.running) this.running = this.go().finally(() => { this.running = null; });
    return this.running;
  }

  /** How many records are waiting to be uploaded. */
  async pending(): Promise<number> {
    const r = await this.db.getFirstAsync<{ n: number }>(
      `SELECT (SELECT COUNT(*) FROM attempts WHERE synced = 0 AND question_id > 0)
            + (SELECT COUNT(*) FROM bookmarks WHERE synced = 0 AND question_id > 0)
            + (SELECT COUNT(*) FROM mocks WHERE synced = 0) AS n`,
    );
    return r?.n ?? 0;
  }

  private async go(): Promise<SyncResult> {
    const pushed = await this.push();
    const pulled = await this.pull();
    return { pushed, pulled };
  }

  private async push(): Promise<SyncResult['pushed']> {
    const total = { attempts: 0, bookmarks: 0, mocks: 0 };

    for (let round = 0; round < 50; round++) {
      const attempts = await unsyncedAttempts(this.db, 200);
      const marks = await unsyncedBookmarks(this.db, 200);
      const mocks = await unsyncedMocks(this.db, 20);
      if (!attempts.length && !marks.length && !mocks.length) break;

      const body = {
        attempts: attempts.map((a) => ({ client_uuid: a.uuid, question_id: a.question_id, mode: a.mode, selected: a.selected, time_ms: a.time_ms, answered_at: a.answered_at })),
        bookmarks: marks.map((b) => ({ question_id: b.question_id, bookmarked: b.is_bookmarked === 1, changed_at: b.changed_at })),
        mock_sessions: mocks.map((m) => ({
          client_uuid: m.uuid, exam_id: m.exam_id, score: m.score, total: m.total, duration_seconds: m.duration_seconds,
          taken_at: m.taken_at, subject_scores: JSON.parse(m.subject_scores),
        })),
      };

      try {
        await this.api.post('/sync/progress', body);
      } catch (e) {
        // A batch the server will never accept (422) is dropped so it cannot block everything behind it.
        if (!(e instanceof ApiError) || e.kind !== 'validation') throw e;
      }

      await markAttemptsSynced(this.db, attempts.map((a) => a.uuid));
      await markBookmarksSynced(this.db, marks.map((b) => b.question_id));
      await markMocksSynced(this.db, mocks.map((m) => m.uuid));
      total.attempts += attempts.length;
      total.bookmarks += marks.length;
      total.mocks += mocks.length;
    }

    return total;
  }

  private async pull(): Promise<SyncResult['pulled']> {
    const total = { attempts: 0, bookmarks: 0, mocks: 0 };
    let cursor = Number((await kvGet(this.db, CURSOR)) ?? 0);
    const since = await kvGet(this.db, SINCE);
    const index = this.index();

    for (let page = 0; page < 200; page++) {
      const q = new URLSearchParams({ cursor: String(cursor) });
      if (since) q.set('since', since);
      const { data } = await this.api.get<PullResponse>(`/sync/progress?${q.toString()}`);

      await this.db.withTransactionAsync(async () => {
        for (const a of data.attempts) {
          const exam = a.exam_id !== null ? index.examById(a.exam_id) : null;
          const subject = a.subject_id !== null ? index.subjectById(a.subject_id) : null;
          const inserted = await recordAttempt(this.db, {
            uuid: a.client_uuid, questionId: a.question_id, examId: a.exam_id, examSlug: exam?.slug ?? null,
            subjectId: a.subject_id, subjectSlug: subject?.slug ?? null, subjectName: subject?.display_name ?? subject?.name ?? null,
            year: a.year, topicId: a.topic_id, topicName: null, mode: a.mode, selected: a.selected, isCorrect: a.is_correct,
            timeMs: a.time_ms, answeredAt: a.answered_at, synced: true,
          });
          if (inserted) total.attempts++;
        }

        for (const b of data.bookmarks) {
          const exam = b.exam_id !== null ? index.examById(b.exam_id) : null;
          const subject = b.subject_id !== null ? index.subjectById(b.subject_id) : null;
          await setBookmark(this.db, {
            questionId: b.question_id, examId: b.exam_id, subjectId: b.subject_id, examSlug: exam?.slug ?? null,
            subjectSlug: subject?.slug ?? null, bookmarked: b.bookmarked, changedAt: b.changed_at, synced: true,
          });
          total.bookmarks++;
        }

        for (const m of data.mock_sessions) {
          await saveMock(this.db, {
            uuid: m.client_uuid, examId: m.exam_id, examName: index.examById(m.exam_id)?.name ?? '', score: m.score, total: m.total,
            durationSeconds: m.duration_seconds, takenAt: m.taken_at, subjectScores: m.subject_scores, synced: true,
          });
          total.mocks++;
        }
      });

      cursor = data.next_cursor;
      await kvSet(this.db, CURSOR, String(cursor));

      if (!data.has_more) {
        await kvSet(this.db, SINCE, data.server_time);
        break;
      }
    }

    return total;
  }
}

/** Ids of the questions currently bookmarked, for showing the bookmark state in a session. */
export { bookmarkedIds };
