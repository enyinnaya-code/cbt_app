import { isNewer } from '@/core/version';
import { kvGet, kvSet } from '@/db/progress';
import type { Db } from '@/db/types';
import type { Api } from './api';

export interface UpdateOffer {
  latest: string;
  url: string;
}

const OFFER = 'update.offer';
const CHECKED = 'update.checked';
const DISMISSED = 'update.dismissed';

/** Ask the server at most this often; in between, the last answer is used. */
export const CHECK_EVERY_MS = 6 * 60 * 60 * 1000;

/**
 * The update link opens in the phone's browser, so it may only lead to our own website or to Google Play, whatever the
 * server (or someone between the phone and it) says. Plain http is only for a development server.
 */
export function safeUpdateUrl(url: unknown, apiBase: string): string | null {
  if (typeof url !== 'string') return null;
  const link = /^(https?):\/\/([^/?#]+)/i.exec(url);
  const api = /^(https?):\/\/([^/?#]+)/i.exec(apiBase);
  if (!link || !api) return null;

  const host = link[2].toLowerCase();
  const devServer = typeof __DEV__ !== 'undefined' && __DEV__;
  const secure = link[1].toLowerCase() === 'https' || devServer;

  return secure && (host === api[2].toLowerCase() || host === 'play.google.com') ? url : null;
}

async function remembered(db: Db): Promise<UpdateOffer | null> {
  try {
    const raw = await kvGet(db, OFFER);
    return raw ? (JSON.parse(raw) as UpdateOffer) : null;
  } catch {
    return null;
  }
}

/**
 * Is there a newer app than the one on this phone, that the student has not already dismissed? Works offline: the last
 * answer is remembered, so an old app keeps reminding until it is updated.
 */
export async function checkForUpdate(db: Db, api: Api, installed: string, now: number = Date.now()): Promise<UpdateOffer | null> {
  const last = Number((await kvGet(db, CHECKED)) ?? 0);
  let offer = await remembered(db);

  if (!offer || now - last >= CHECK_EVERY_MS || now < last) {
    try {
      const { data } = await api.get<{ latest: string | null; update_url: string | null }>('/app-version');
      const url = safeUpdateUrl(data?.update_url, api.baseUrl);
      offer = data && typeof data.latest === 'string' && url ? { latest: data.latest, url } : null;
      await kvSet(db, OFFER, JSON.stringify(offer));
      await kvSet(db, CHECKED, String(now));
    } catch {
      // Offline or a server hiccup: keep whatever was known.
    }
  }

  if (!offer || !isNewer(offer.latest, installed)) return null;
  if ((await kvGet(db, DISMISSED)) === offer.latest) return null;

  return offer;
}

/** "Later": hide the banner for this version. A still newer version brings it back. */
export async function dismissUpdate(db: Db, latest: string): Promise<void> {
  await kvSet(db, DISMISSED, latest);
}
