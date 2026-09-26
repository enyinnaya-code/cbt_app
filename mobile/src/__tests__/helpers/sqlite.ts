/**
 * Runs the app's real SQL in memory. The app's Db interface is implemented over better-sqlite3, so
 * migrations, queries and stats are tested exactly as written, not against a fake.
 */
import Database from 'better-sqlite3';
import { migrate } from '@/db/schema';
import type { Db } from '@/db/types';

export function rawDb(): Db {
  const d = new Database(':memory:');

  return {
    execAsync: async (sql) => { d.exec(sql); },
    runAsync: async (sql, params = []) => {
      const r = d.prepare(sql).run(...(params as never[]));
      return { changes: r.changes, lastInsertRowId: Number(r.lastInsertRowid) };
    },
    getAllAsync: async <T,>(sql: string, params: unknown[] = []) => d.prepare(sql).all(...(params as never[])) as T[],
    getFirstAsync: async <T,>(sql: string, params: unknown[] = []) => (d.prepare(sql).get(...(params as never[])) as T | undefined) ?? null,
    withTransactionAsync: async (task) => {
      d.exec('BEGIN');
      try { await task(); d.exec('COMMIT'); } catch (e) { d.exec('ROLLBACK'); throw e; }
    },
  };
}

export async function memoryDb(): Promise<Db> {
  const db = rawDb();
  await migrate(db);
  return db;
}
