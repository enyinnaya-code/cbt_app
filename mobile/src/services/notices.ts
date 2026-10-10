import { kvGet, kvSet } from '@/db/progress';
import type { Db } from '@/db/types';
import type { Api } from './api';

export interface Notice {
  id: number;
  title: string;
  body: string | null;
  tone: 'info' | 'success' | 'warning';
  link_url: string | null;
  link_label: string | null;
}

const CACHE = 'notices.cache';
const CLOSED = 'notices.closed';

function usable(x: unknown): x is Notice[] {
  return Array.isArray(x) && x.every((n) => n && typeof n.id === 'number' && typeof n.title === 'string');
}

/** A link opens in the phone's browser, so only web links are allowed, whatever the server says. */
export function safeLink(url: unknown): string | null {
  return typeof url === 'string' && /^https?:\/\/[^\s/]+/i.test(url) ? url : null;
}

async function closedIds(db: Db): Promise<number[]> {
  try {
    const raw = await kvGet(db, CLOSED);
    const list = raw ? JSON.parse(raw) : [];
    return Array.isArray(list) ? list.filter((n) => typeof n === 'number') : [];
  } catch {
    return [];
  }
}

/**
 * The announcements to show on Home: the server's current list (the last one when offline), minus the ones the student
 * closed on this phone. A closed announcement stays closed; a new one still shows.
 */
export async function currentNotices(db: Db, api: Api): Promise<Notice[]> {
  let list: Notice[] = [];
  try {
    const { data } = await api.get<{ notices: Notice[] }>('/notices');
    if (usable(data?.notices)) {
      list = data.notices;
      await kvSet(db, CACHE, JSON.stringify(list));
    }
  } catch {
    try {
      const raw = await kvGet(db, CACHE);
      const saved = raw ? JSON.parse(raw) : [];
      if (usable(saved)) list = saved;
    } catch {
      list = [];
    }
  }

  const closed = await closedIds(db);
  return list.filter((n) => !closed.includes(n.id));
}

export async function closeNotice(db: Db, id: number): Promise<void> {
  const closed = await closedIds(db);
  if (!closed.includes(id)) await kvSet(db, CLOSED, JSON.stringify([...closed, id].slice(-50)));
}
