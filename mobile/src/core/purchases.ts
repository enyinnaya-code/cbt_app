import type { Catalog } from './types';
import type { SubjectView } from './views';

/** Show a "renew" reminder this many days before a subject ends (the website uses the same number). */
export const REMIND_DAYS = 14;

/** A pack at most this big is swapped in without asking, even on mobile data: the student has just paid for it. */
export const SMALL_PACK_BYTES = 1_500_000;

/**
 * After a purchase the free sample on the phone must be replaced by the full set. Should that happen now, on its own?
 * Yes when the phone is on Wi-Fi, or when "Wi-Fi only" is off, or when the download is small. A big one on mobile data
 * waits for the student's say-so (the Home screen offers it).
 */
export function replaceNow(sizeBytes: number, wifiOnly: boolean, onWifi: boolean): boolean {
  return !wifiOnly || onWifi || sizeBytes <= SMALL_PACK_BYTES;
}

/** Subjects the student has unlocked whose phone copy is still the free sample. */
export function pendingUpgrades(views: { subjects: SubjectView[] }[]): SubjectView[] {
  return views.flatMap((v) => v.subjects).filter((s) =>
    !!s.installed && !s.installed.starter && s.installed.tier === 'free' && !s.locked && s.offered?.tier === 'full');
}

export interface Ending {
  examSlug: string;
  subjectSlug: string;
  subject: string;
  exam: string;
  days: number;
  endsAt: string;
}

/**
 * Subjects the catalog says end within `within` days (and have not ended). Worked out from the catalog the phone already
 * has, so the reminder shows offline. Part of a day counts as a day, as on the website.
 */
export function endingSoon(catalog: Catalog | null, now: Date = new Date(), within: number = REMIND_DAYS): Ending[] {
  const out: Ending[] = [];

  for (const exam of catalog?.exams ?? []) {
    for (const s of exam.subjects) {
      if (!s.expires_at) continue;
      const ends = new Date(s.expires_at).getTime();
      if (Number.isNaN(ends)) continue;

      const days = Math.ceil((ends - now.getTime()) / 86_400_000);
      if (ends > now.getTime() && days <= within) {
        out.push({ examSlug: exam.slug, subjectSlug: s.slug, subject: s.display_name || s.name, exam: exam.name, days, endsAt: s.expires_at });
      }
    }
  }

  return out.sort((a, b) => a.days - b.days);
}

export interface Unlocked {
  exam: string;
  exam_slug: string;
  subject: string;
  subject_slug: string;
  expires_at: string;
  days_left: number;
  expired: boolean;
}

export interface Payment {
  reference: string;
  exam: string;
  exam_slug: string;
  subjects: string[];
  amount: number;
  currency: string;
  method: string;
  method_label: string;
  status: string;
  status_label: string;
  created_at: string;
  paid_at: string | null;
}

export interface PurchasesData {
  remind_days: number;
  unlocked: Unlocked[];
  payments: Payment[];
}
