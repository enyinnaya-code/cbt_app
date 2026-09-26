/**
 * Builds the app's long-lived services once, at start-up, and hands them out. Screens never open the database
 * or talk to the server themselves; they go through these.
 */
import * as Device from 'expo-device';
import type { Db } from '@/db/types';
import { migrate } from '@/db/schema';
import { downloader, newUuid, openDb, packFiles, sha256Hex } from '@/platform/native';
import { Api } from '@/services/api';
import { CatalogIndex } from '@/services/catalog';
import { PackManager } from '@/services/packs';
import { SyncEngine } from '@/services/sync';
import { useSession } from './session';
import { useData } from './data';

export interface Services {
  db: Db;
  api: Api;
  packs: PackManager;
  sync: SyncEngine;
  newUuid: () => string;
}

let current: Services | null = null;

/** Where the API lives. Override with EXPO_PUBLIC_API_URL when testing against another server. */
export const API_URL = process.env.EXPO_PUBLIC_API_URL ?? 'https://testacbt.com/api/v1';

export async function initServices(): Promise<Services> {
  if (current) return current;

  const db = await openDb();
  await migrate(db);

  const api = new Api({
    baseUrl: API_URL,
    getToken: () => useSession.getState().token,
    onUnauthorized: () => useSession.getState().markNeedsReauth(),
    deviceName: () => Device.deviceName ?? Device.modelName ?? 'Phone',
  });

  current = {
    db,
    api,
    packs: new PackManager(db, packFiles, downloader, sha256Hex, api),
    sync: new SyncEngine(db, api, () => useData.getState().index),
    newUuid,
  };
  return current;
}

export function services(): Services {
  if (!current) throw new Error('Services are not ready yet.');
  return current;
}

export const emptyIndex = () => new CatalogIndex(null);
