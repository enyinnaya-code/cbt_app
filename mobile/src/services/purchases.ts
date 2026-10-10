import type { PurchasesData } from '@/core/purchases';
import { kvGet, kvSet } from '@/db/progress';
import type { Db } from '@/db/types';
import type { Api } from './api';

const KEY = 'purchases.cache';

export interface PurchasesResult {
  data: PurchasesData | null;
  /** True when this is the copy from the last time the phone was online. */
  stale: boolean;
}

export async function cachedPurchases(db: Db): Promise<PurchasesData | null> {
  try {
    const raw = await kvGet(db, KEY);
    return raw ? (JSON.parse(raw) as PurchasesData) : null;
  } catch {
    return null;
  }
}

function usable(x: unknown): x is PurchasesData {
  const d = x as PurchasesData | null;
  return !!d && Array.isArray(d.unlocked) && Array.isArray(d.payments);
}

/**
 * The student's unlocked subjects and payments. Online it asks the server and remembers the answer; offline (or on a
 * server hiccup) it shows the last answer, marked as old, so the screen still works without internet.
 */
export async function loadPurchases(db: Db, api: Api): Promise<PurchasesResult> {
  try {
    const { data } = await api.get<PurchasesData>('/purchases');
    if (usable(data)) {
      await kvSet(db, KEY, JSON.stringify(data));
      return { data, stale: false };
    }
  } catch {
    // fall through to the saved copy
  }

  return { data: await cachedPurchases(db), stale: true };
}
