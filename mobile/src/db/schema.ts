import type { Db } from './types';

/**
 * Versioned migrations, run on every start. PRAGMA user_version remembers how far this phone has got, so an
 * app update only applies what is new and never touches a student's progress.
 */
const MIGRATIONS: string[] = [
  // 1: everything the first release needs
  `
  CREATE TABLE kv (key TEXT PRIMARY KEY, value TEXT NOT NULL);

  CREATE TABLE attempts (
    uuid TEXT PRIMARY KEY,
    question_id INTEGER NOT NULL,
    exam_id INTEGER, exam_slug TEXT,
    subject_id INTEGER, subject_slug TEXT, subject_name TEXT,
    year INTEGER,
    topic_id INTEGER, topic_name TEXT,
    mode TEXT NOT NULL,
    selected TEXT,
    is_correct INTEGER NOT NULL,
    time_ms INTEGER,
    answered_at TEXT NOT NULL,
    day TEXT NOT NULL,
    synced INTEGER NOT NULL DEFAULT 0
  );
  CREATE INDEX attempts_synced ON attempts (synced);
  CREATE INDEX attempts_day ON attempts (day);
  CREATE INDEX attempts_subject ON attempts (subject_id);

  CREATE TABLE bookmarks (
    question_id INTEGER PRIMARY KEY,
    exam_id INTEGER, subject_id INTEGER,
    exam_slug TEXT, subject_slug TEXT,
    is_bookmarked INTEGER NOT NULL,
    changed_at TEXT NOT NULL,
    synced INTEGER NOT NULL DEFAULT 0
  );

  CREATE TABLE mocks (
    uuid TEXT PRIMARY KEY,
    exam_id INTEGER NOT NULL, exam_name TEXT,
    score INTEGER NOT NULL, total INTEGER NOT NULL,
    duration_seconds INTEGER NOT NULL,
    taken_at TEXT NOT NULL,
    subject_scores TEXT NOT NULL,
    synced INTEGER NOT NULL DEFAULT 0
  );

  -- A mock exam in progress. "state" is the paper (written once); "progress" is answers, flags and position
  -- (small, written often), so a 180-question exam is not rewritten on every tap.
  CREATE TABLE mock_runs (
    uuid TEXT PRIMARY KEY,
    state TEXT NOT NULL,
    progress TEXT NOT NULL DEFAULT '{}',
    finished INTEGER NOT NULL DEFAULT 0,
    updated_at TEXT NOT NULL
  );

  CREATE TABLE packs (
    exam_slug TEXT NOT NULL, subject_slug TEXT NOT NULL,
    exam_name TEXT NOT NULL DEFAULT '', subject_name TEXT NOT NULL DEFAULT '', display_name TEXT NOT NULL DEFAULT '',
    version INTEGER NOT NULL,
    sha256 TEXT,
    size_bytes INTEGER NOT NULL,
    question_count INTEGER NOT NULL,
    paper_count INTEGER NOT NULL,
    years TEXT NOT NULL,
    starter INTEGER NOT NULL DEFAULT 0,
    installed_at TEXT NOT NULL,
    PRIMARY KEY (exam_slug, subject_slug)
  );
  `,
];

export async function migrate(db: Db): Promise<void> {
  const row = await db.getFirstAsync<{ user_version: number }>('PRAGMA user_version');
  let version = row?.user_version ?? 0;

  while (version < MIGRATIONS.length) {
    await db.withTransactionAsync(async () => {
      await db.execAsync(MIGRATIONS[version]);
      await db.execAsync(`PRAGMA user_version = ${version + 1}`);
    });
    version++;
  }
}

export const SCHEMA_VERSION = MIGRATIONS.length;
