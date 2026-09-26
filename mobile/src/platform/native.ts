/**
 * The only file that talks to Expo's native modules. Everything else takes these as plain interfaces, which
 * is what lets the logic be tested on a computer. APIs follow the Expo SDK 57 documentation.
 */
import * as Crypto from 'expo-crypto';
import { Directory, File, Paths } from 'expo-file-system';
import * as SecureStore from 'expo-secure-store';
import * as SQLite from 'expo-sqlite';
import type { Db } from '@/db/types';
import type { Downloader, PackFiles, Sha256 } from '@/services/packs';

export async function openDb(): Promise<Db> {
  const db = await SQLite.openDatabaseAsync('testacbt.db');
  await db.execAsync('PRAGMA journal_mode = WAL;');

  return {
    execAsync: (sql) => db.execAsync(sql),
    runAsync: async (sql, params = []) => {
      const r = await db.runAsync(sql, params as SQLite.SQLiteBindParams);
      return { changes: r.changes, lastInsertRowId: r.lastInsertRowId };
    },
    getAllAsync: <T>(sql: string, params: unknown[] = []) => db.getAllAsync<T>(sql, params as SQLite.SQLiteBindParams),
    getFirstAsync: <T>(sql: string, params: unknown[] = []) => db.getFirstAsync<T>(sql, params as SQLite.SQLiteBindParams),
    withTransactionAsync: (task) => db.withTransactionAsync(task),
  };
}

// ---------------------------------------------------------------------------------------------- pack files

const packsDir = () => new Directory(Paths.document, 'packs');
const packFile = (exam: string, subject: string) => new File(packsDir(), `${exam}-${subject}.json`);

export const packFiles: PackFiles = {
  async write(exam, subject, json) {
    const dir = packsDir();
    if (!dir.exists) dir.create({ intermediates: true });
    const file = packFile(exam, subject);
    if (!file.exists) file.create();
    file.write(json);
  },
  async read(exam, subject) {
    const file = packFile(exam, subject);
    return file.exists ? file.text() : null;
  },
  async remove(exam, subject) {
    const file = packFile(exam, subject);
    if (file.exists) file.delete();
  },
};

/** Downloads to a temporary file (so a huge pack never sits in memory as text), then hands back the bytes. */
export const downloader: Downloader = {
  async download(url, headers, onProgress, signal) {
    const dir = new Directory(Paths.cache, 'downloads');
    if (!dir.exists) dir.create({ intermediates: true });
    const target = new File(dir, `pack-${Date.now()}.gz`);

    const file = await File.downloadFileAsync(url, target, {
      headers,
      idempotent: true,
      signal,
      onProgress: (p) => onProgress(p.bytesWritten, p.totalBytes),
    });

    try {
      return await file.bytes();
    } finally {
      if (file.exists) file.delete();
    }
  },
};

export const sha256Hex: Sha256 = async (bytes) => {
  // The bytes come from a file read, never a SharedArrayBuffer; the cast only satisfies the strict typing.
  const digest = await Crypto.digest(Crypto.CryptoDigestAlgorithm.SHA256, bytes as Uint8Array<ArrayBuffer>);
  return Array.from(new Uint8Array(digest)).map((b) => b.toString(16).padStart(2, '0')).join('');
};

export const newUuid = () => Crypto.randomUUID();

// ---------------------------------------------------------------------------------------------- sign-in token

const TOKEN_KEY = 'tc.token';

export const tokenStore = {
  get: () => SecureStore.getItemAsync(TOKEN_KEY),
  set: (token: string) => SecureStore.setItemAsync(TOKEN_KEY, token),
  clear: () => SecureStore.deleteItemAsync(TOKEN_KEY),
};

/** Free space on the phone, for "1.2 GB free" and for refusing a download that cannot fit. */
export function freeDiskBytes(): number {
  try { return Paths.availableDiskSpace; } catch { return Number.MAX_SAFE_INTEGER; }
}
