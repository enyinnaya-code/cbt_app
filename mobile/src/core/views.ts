/**
 * One list of exams and subjects for the screens to use, built from the server's catalog when the phone has it
 * and from the installed packs when it does not (first launch with no internet). Screens never need to know which.
 */
import type { Catalog, CatalogPack, CatalogSubject, MockFormat, PackTier } from './types';

export interface InstalledInfo {
  exam_slug: string;
  subject_slug: string;
  exam_name: string;
  subject_name: string;
  display_name: string;
  tier: PackTier;
  version: number;
  size_bytes: number;
  question_count: number;
  years: number[];
  starter: boolean;
}

export interface SubjectView {
  examSlug: string;
  examName: string;
  slug: string;
  /** The exam's own name for the subject. */
  name: string;
  code: string;
  /** Position in its exam, for the rotating tile colours. */
  index: number;
  subjectId: number | null;
  /** What the server offers (null when offline with no catalog, or nothing published yet). */
  offered: CatalogPack | null;
  installed: InstalledInfo | null;
  /** A newer pack is available than the one on the phone, or the other tier (unlocked or run out). */
  updateAvailable: boolean;
  /** Only the free sample is open to this student. Never true for the bundled starter questions or when it is not known. */
  locked: boolean;
  /** Naira to unlock (0 when free or unknown). */
  price: number;
  freeQuestions: number;
  /** Questions in the full subject, when the server said. */
  fullQuestions: number | null;
  /** When a purchase ends. */
  expiresAt: string | null;
  /** Size to show before downloading. */
  downloadBytes: number;
}

export interface ExamView {
  slug: string;
  name: string;
  examId: number | null;
  subjects: SubjectView[];
}

/**
 * Should the pack the catalog offers replace the one on the phone? A version number only means something within the
 * same tier (full and free are numbered separately), so a different tier always replaces.
 */
export function needsReplacing(installed: { tier: PackTier; version: number; starter: boolean }, offered: { tier?: PackTier; version: number }): boolean {
  if (installed.starter) return true;
  if ((offered.tier ?? 'full') !== installed.tier) return true;
  return offered.version > installed.version;
}

export function codeOf(name: string, fallback?: string): string {
  if (fallback) return fallback;
  const words = name.split(/\s+/).filter((w) => w && !['of', 'and', 'the', 'in', 'for', 'to'].includes(w.toLowerCase()));
  return words.length > 1 ? (words[0][0] + words[1][0]).toUpperCase() : name.slice(0, 2).replace(/^./, (c) => c.toUpperCase());
}

export function buildViews(catalog: Catalog | null, installed: InstalledInfo[]): ExamView[] {
  const have = new Map(installed.map((i) => [`${i.exam_slug}/${i.subject_slug}`, i]));
  const exams: ExamView[] = [];
  const seen = new Set<string>();

  for (const e of catalog?.exams ?? []) {
    const subjects = e.subjects.map((s: CatalogSubject, i): SubjectView => {
      const inst = have.get(`${e.slug}/${s.slug}`) ?? null;
      seen.add(`${e.slug}/${s.slug}`);
      return {
        examSlug: e.slug, examName: e.name, slug: s.slug, name: s.display_name || s.name, code: s.code, index: i, subjectId: s.id,
        offered: s.pack, installed: inst,
        updateAvailable: !!(inst && s.pack && needsReplacing(inst, s.pack)),
        // The bundled sample questions are never "locked": they are the app's own content. Everything else follows the server.
        locked: s.access === 'free' && !(inst?.starter && !s.pack),
        price: s.price ?? 0,
        freeQuestions: s.free_questions ?? 0,
        fullQuestions: s.full_question_count ?? null,
        expiresAt: s.expires_at ?? null,
        downloadBytes: s.pack?.size_bytes ?? 0,
      };
    });
    exams.push({ slug: e.slug, name: e.name, examId: e.id, subjects });
  }

  // Packs on the phone that the catalog does not mention (or when there is no catalog at all).
  for (const inst of installed) {
    if (seen.has(`${inst.exam_slug}/${inst.subject_slug}`)) continue;
    let exam = exams.find((x) => x.slug === inst.exam_slug);
    if (!exam) { exam = { slug: inst.exam_slug, name: inst.exam_name || inst.exam_slug.toUpperCase(), examId: null, subjects: [] }; exams.push(exam); }

    const display = inst.display_name || inst.subject_name;
    exam.subjects.push({
      examSlug: inst.exam_slug, examName: exam.name, slug: inst.subject_slug, name: display, code: codeOf(display), index: exam.subjects.length,
      subjectId: null, offered: null, installed: inst, updateAvailable: false,
      // Without a catalog we only know what is on the phone: a downloaded sample is still a sample.
      locked: inst.tier === 'free' && !inst.starter, price: 0, freeQuestions: 0, fullQuestions: null, expiresAt: null, downloadBytes: 0,
    });
  }

  return exams;
}

/** Formats the app knows without the server, mirroring config/testacbt.php. The catalog's copy wins when present. */
export const DEFAULT_FORMATS: Record<string, MockFormat> = {
  jamb: { label: 'JAMB UTME', subject_count: 4, compulsory: 'english-language', questions: { 'english-language': 60, default: 40 }, minutes: 120, score_max: 400 },
  waec: { label: 'WAEC objective', subject_count: 1, compulsory: null, questions: { default: 50 }, minutes: 60, score_max: 100 },
  neco: { label: 'NECO objective', subject_count: 1, compulsory: null, questions: { default: 50 }, minutes: 60, score_max: 100 },
  'post-utme': { label: 'Post-UTME', subject_count: 1, compulsory: null, questions: { default: 40 }, minutes: 45, score_max: 100 },
  igcse: { label: 'IGCSE multiple choice', subject_count: 1, compulsory: null, questions: { default: 40 }, minutes: 45, score_max: 100 },
};

export function formatFor(catalog: Catalog | null, examSlug: string, examName: string): MockFormat {
  return catalog?.mock?.[examSlug] ?? DEFAULT_FORMATS[examSlug] ?? { label: `${examName} mock`, subject_count: 1, compulsory: null, questions: { default: 50 }, minutes: 60, score_max: 100 };
}
