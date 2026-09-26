/**
 * Everything the student has done, stored on the phone: answers, bookmarks and mock exams.
 * Anything not yet uploaded is flagged synced = 0 and goes up when the phone is online.
 */
import type { Db } from './types';

export interface AttemptInput {
  uuid: string;
  questionId: number;
  examId: number | null;
  examSlug: string | null;
  subjectId: number | null;
  subjectSlug: string | null;
  subjectName: string | null;
  year: number | null;
  topicId: number | null;
  topicName: string | null;
  mode: 'practice' | 'mock';
  selected: string | null;
  isCorrect: boolean;
  timeMs: number | null;
  answeredAt: string; // ISO 8601
  synced?: boolean;
}

/** The local calendar day of an ISO time, as YYYY-MM-DD. Streaks are counted in the student's own day. */
export function localDay(iso: string): string {
  const d = new Date(iso);
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

export async function recordAttempt(db: Db, a: AttemptInput): Promise<boolean> {
  const r = await db.runAsync(
    `INSERT OR IGNORE INTO attempts
      (uuid, question_id, exam_id, exam_slug, subject_id, subject_slug, subject_name, year, topic_id, topic_name,
       mode, selected, is_correct, time_ms, answered_at, day, synced)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)`,
    [a.uuid, a.questionId, a.examId, a.examSlug, a.subjectId, a.subjectSlug, a.subjectName, a.year, a.topicId, a.topicName,
      a.mode, a.selected, a.isCorrect ? 1 : 0, a.timeMs, a.answeredAt, localDay(a.answeredAt), a.synced ? 1 : 0],
  );
  return r.changes > 0;
}

export interface UnsyncedAttempt {
  uuid: string;
  question_id: number;
  mode: string;
  selected: string | null;
  time_ms: number | null;
  answered_at: string;
}

/** Real questions only: the bundled starter questions have negative ids and never leave the phone. */
export function unsyncedAttempts(db: Db, limit = 200): Promise<UnsyncedAttempt[]> {
  return db.getAllAsync<UnsyncedAttempt>(
    'SELECT uuid, question_id, mode, selected, time_ms, answered_at FROM attempts WHERE synced = 0 AND question_id > 0 ORDER BY answered_at LIMIT ?',
    [limit],
  );
}

export async function markAttemptsSynced(db: Db, uuids: string[]): Promise<void> {
  for (let i = 0; i < uuids.length; i += 200) {
    const chunk = uuids.slice(i, i + 200);
    await db.runAsync(`UPDATE attempts SET synced = 1 WHERE uuid IN (${chunk.map(() => '?').join(',')})`, chunk);
  }
}

// ---------------------------------------------------------------------------------------------- bookmarks

export interface BookmarkInput {
  questionId: number;
  examId: number | null;
  subjectId: number | null;
  examSlug: string | null;
  subjectSlug: string | null;
  bookmarked: boolean;
  changedAt: string;
  synced?: boolean;
}

/** Newest change wins, as on the server. A pulled change older than what is on the phone is ignored. */
export async function setBookmark(db: Db, b: BookmarkInput): Promise<void> {
  const existing = await db.getFirstAsync<{ changed_at: string }>('SELECT changed_at FROM bookmarks WHERE question_id = ?', [b.questionId]);
  if (existing && new Date(existing.changed_at).getTime() > new Date(b.changedAt).getTime()) return;

  await db.runAsync(
    `INSERT INTO bookmarks (question_id, exam_id, subject_id, exam_slug, subject_slug, is_bookmarked, changed_at, synced)
     VALUES (?,?,?,?,?,?,?,?)
     ON CONFLICT(question_id) DO UPDATE SET
       exam_id = COALESCE(excluded.exam_id, exam_id), subject_id = COALESCE(excluded.subject_id, subject_id),
       exam_slug = COALESCE(excluded.exam_slug, exam_slug), subject_slug = COALESCE(excluded.subject_slug, subject_slug),
       is_bookmarked = excluded.is_bookmarked, changed_at = excluded.changed_at, synced = excluded.synced`,
    [b.questionId, b.examId, b.subjectId, b.examSlug, b.subjectSlug, b.bookmarked ? 1 : 0, b.changedAt, b.synced ? 1 : 0],
  );
}

export interface BookmarkRow {
  question_id: number;
  exam_id: number | null;
  subject_id: number | null;
  exam_slug: string | null;
  subject_slug: string | null;
  changed_at: string;
}

export function bookmarks(db: Db): Promise<BookmarkRow[]> {
  return db.getAllAsync<BookmarkRow>(
    'SELECT question_id, exam_id, subject_id, exam_slug, subject_slug, changed_at FROM bookmarks WHERE is_bookmarked = 1 ORDER BY changed_at DESC',
  );
}

export async function bookmarkedIds(db: Db): Promise<Set<number>> {
  const rows = await db.getAllAsync<{ question_id: number }>('SELECT question_id FROM bookmarks WHERE is_bookmarked = 1');
  return new Set(rows.map((r) => r.question_id));
}

export function unsyncedBookmarks(db: Db, limit = 200) {
  return db.getAllAsync<{ question_id: number; is_bookmarked: number; changed_at: string }>(
    'SELECT question_id, is_bookmarked, changed_at FROM bookmarks WHERE synced = 0 AND question_id > 0 LIMIT ?',
    [limit],
  );
}

export async function markBookmarksSynced(db: Db, ids: number[]): Promise<void> {
  for (let i = 0; i < ids.length; i += 200) {
    const chunk = ids.slice(i, i + 200);
    await db.runAsync(`UPDATE bookmarks SET synced = 1 WHERE question_id IN (${chunk.map(() => '?').join(',')})`, chunk);
  }
}

// ---------------------------------------------------------------------------------------------- mock exams

export interface MockRecord {
  uuid: string;
  examId: number;
  examName: string;
  score: number;
  total: number;
  durationSeconds: number;
  takenAt: string;
  subjectScores: { subject_id: number; correct: number; total: number }[];
  synced?: boolean;
}

export async function saveMock(db: Db, m: MockRecord): Promise<void> {
  await db.runAsync(
    `INSERT OR IGNORE INTO mocks (uuid, exam_id, exam_name, score, total, duration_seconds, taken_at, subject_scores, synced)
     VALUES (?,?,?,?,?,?,?,?,?)`,
    [m.uuid, m.examId, m.examName, m.score, m.total, m.durationSeconds, m.takenAt, JSON.stringify(m.subjectScores), m.synced ? 1 : 0],
  );
}

export interface MockRow {
  uuid: string;
  exam_id: number;
  exam_name: string | null;
  score: number;
  total: number;
  duration_seconds: number;
  taken_at: string;
  subject_scores: string;
}

export function mocks(db: Db, limit = 20): Promise<MockRow[]> {
  return db.getAllAsync<MockRow>('SELECT * FROM mocks ORDER BY taken_at DESC LIMIT ?', [limit]);
}

export function unsyncedMocks(db: Db, limit = 20): Promise<MockRow[]> {
  return db.getAllAsync<MockRow>('SELECT * FROM mocks WHERE synced = 0 ORDER BY taken_at LIMIT ?', [limit]);
}

export async function markMocksSynced(db: Db, uuids: string[]): Promise<void> {
  if (!uuids.length) return;
  await db.runAsync(`UPDATE mocks SET synced = 1 WHERE uuid IN (${uuids.map(() => '?').join(',')})`, uuids);
}

/** Points versus the previous mock for the same exam (same total), or null for a first mock. */
export async function changeSincePrevious(db: Db, mock: { uuid: string; examId: number; score: number; total: number; takenAt: string }): Promise<number | null> {
  const prev = await db.getFirstAsync<{ score: number }>(
    'SELECT score FROM mocks WHERE exam_id = ? AND total = ? AND taken_at < ? AND uuid != ? ORDER BY taken_at DESC LIMIT 1',
    [mock.examId, mock.total, mock.takenAt, mock.uuid],
  );
  return prev ? mock.score - prev.score : null;
}

// ---------------------------------------------------------------------------------------------- stats

export interface SubjectStat {
  subject_id: number | null;
  subject_slug: string | null;
  subject_name: string | null;
  answered: number;
  correct: number;
  accuracy: number;
}

export async function totals(db: Db): Promise<{ answered: number; correct: number }> {
  const r = await db.getFirstAsync<{ answered: number; correct: number | null }>('SELECT COUNT(*) AS answered, SUM(is_correct) AS correct FROM attempts');
  return { answered: r?.answered ?? 0, correct: r?.correct ?? 0 };
}

/** Accuracy per subject, weakest first. */
export async function subjectStats(db: Db): Promise<SubjectStat[]> {
  const rows = await db.getAllAsync<Omit<SubjectStat, 'accuracy'>>(
    `SELECT subject_id, subject_slug, MAX(subject_name) AS subject_name, COUNT(*) AS answered, SUM(is_correct) AS correct
     FROM attempts WHERE subject_id IS NOT NULL GROUP BY subject_id, subject_slug`,
  );
  return rows
    .map((r) => ({ ...r, correct: r.correct ?? 0, accuracy: r.answered ? Math.round(((r.correct ?? 0) / r.answered) * 100) : 0 }))
    .sort((a, b) => a.accuracy - b.accuracy);
}

export interface TopicStat {
  topic_id: number;
  topic_name: string;
  subject_id: number | null;
  subject_slug: string | null;
  subject_name: string | null;
  answered: number;
  correct: number;
  accuracy: number;
}

/** Topics with at least `min` answers, weakest first. */
export async function weakTopics(db: Db, limit = 5, min = 5): Promise<TopicStat[]> {
  const rows = await db.getAllAsync<Omit<TopicStat, 'accuracy'>>(
    `SELECT topic_id, MAX(topic_name) AS topic_name, subject_id, subject_slug, MAX(subject_name) AS subject_name,
            COUNT(*) AS answered, SUM(is_correct) AS correct
     FROM attempts WHERE topic_id IS NOT NULL AND topic_name IS NOT NULL
     GROUP BY topic_id, subject_id, subject_slug HAVING COUNT(*) >= ?`,
    [min],
  );
  return rows
    .map((r) => ({ ...r, correct: r.correct ?? 0, accuracy: Math.round(((r.correct ?? 0) / r.answered) * 100) }))
    .sort((a, b) => a.accuracy - b.accuracy)
    .slice(0, limit);
}

/** Consecutive study days ending today, or yesterday so a streak is not lost before today's first answer. */
export async function streak(db: Db, today: Date = new Date()): Promise<number> {
  const rows = await db.getAllAsync<{ day: string }>('SELECT DISTINCT day FROM attempts');
  const days = new Set(rows.map((r) => r.day));
  const key = (d: Date) => localDay(d.toISOString());

  const cursor = new Date(today.getFullYear(), today.getMonth(), today.getDate(), 12);
  if (!days.has(key(cursor))) cursor.setDate(cursor.getDate() - 1);

  let count = 0;
  while (days.has(key(cursor))) {
    count++;
    cursor.setDate(cursor.getDate() - 1);
  }
  return count;
}

export interface DayCount {
  label: string;
  day: string;
  count: number;
  today: boolean;
}

export async function lastDays(db: Db, days = 7, today: Date = new Date()): Promise<DayCount[]> {
  const start = new Date(today.getFullYear(), today.getMonth(), today.getDate() - (days - 1), 12);
  const rows = await db.getAllAsync<{ day: string; n: number }>('SELECT day, COUNT(*) AS n FROM attempts WHERE day >= ? GROUP BY day', [localDay(start.toISOString())]);
  const counts = new Map(rows.map((r) => [r.day, r.n]));
  const names = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

  return Array.from({ length: days }, (_, i) => {
    const d = new Date(start);
    d.setDate(start.getDate() + i);
    const day = localDay(d.toISOString());
    return { label: names[d.getDay()], day, count: counts.get(day) ?? 0, today: i === days - 1 };
  });
}

export function lastPractised(db: Db) {
  return db.getFirstAsync<{ exam_slug: string; subject_slug: string; subject_name: string; year: number | null; answered_at: string }>(
    `SELECT exam_slug, subject_slug, subject_name, year, answered_at FROM attempts
     WHERE subject_slug IS NOT NULL AND mode = 'practice' ORDER BY answered_at DESC LIMIT 1`,
  );
}

// ---------------------------------------------------------------------------------------------- key-value

export async function kvGet(db: Db, key: string): Promise<string | null> {
  const r = await db.getFirstAsync<{ value: string }>('SELECT value FROM kv WHERE key = ?', [key]);
  return r?.value ?? null;
}

export async function kvSet(db: Db, key: string, value: string): Promise<void> {
  await db.runAsync('INSERT INTO kv (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value', [key, value]);
}

export async function kvDelete(db: Db, key: string): Promise<void> {
  await db.runAsync('DELETE FROM kv WHERE key = ?', [key]);
}

/** Signing out clears the student's records from the phone so the next person starts clean. */
export async function clearStudentData(db: Db): Promise<void> {
  await db.withTransactionAsync(async () => {
    for (const table of ['attempts', 'bookmarks', 'mocks', 'mock_runs']) await db.execAsync(`DELETE FROM ${table}`);
    await db.execAsync("DELETE FROM kv WHERE key LIKE 'sync.%'");
  });
}
