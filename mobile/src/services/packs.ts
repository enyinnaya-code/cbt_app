/**
 * Offline content packs: download, check, unzip, store, load and delete.
 * The file system, the downloader and the hashing are passed in, so the logic is testable without a phone.
 */
import { gunzipSync, strFromU8 } from 'fflate';
import type { Db } from '@/db/types';
import type { CatalogSubject, Pack, PackTier } from '@/core/types';
import { needsReplacing } from '@/core/views';
import type { Api } from './api';

export interface PackMeta {
  exam_slug: string;
  subject_slug: string;
  exam_name: string;
  subject_name: string;
  /** The exam's own name for the subject (JAMB says "Use of English"). */
  display_name: string;
  /** "full" has every question; "free" is the sample (the starter questions count as a sample). */
  tier: PackTier;
  version: number;
  sha256: string | null;
  size_bytes: number;
  question_count: number;
  paper_count: number;
  years: number[];
  starter: boolean;
  installed_at: string;
}

export interface PackFiles {
  write(exam: string, subject: string, json: string): Promise<void>;
  read(exam: string, subject: string): Promise<string | null>;
  remove(exam: string, subject: string): Promise<void>;
}

export interface Downloader {
  download(url: string, headers: Record<string, string>, onProgress: (done: number, total: number) => void, signal?: AbortSignal): Promise<Uint8Array>;
}

export type Sha256 = (bytes: Uint8Array) => Promise<string>;

export type PackErrorKind = 'checksum' | 'format' | 'download' | 'cancelled' | 'newer-app-needed';

export class PackError extends Error {
  constructor(message: string, public readonly kind: PackErrorKind) {
    super(message);
    this.name = 'PackError';
  }
}

/** The pack format this app understands. A higher number means the app is too old to read the file. */
export const SUPPORTED_FORMAT = 1;

interface Row {
  exam_slug: string; subject_slug: string; exam_name: string; subject_name: string; display_name: string; tier: PackTier; version: number; sha256: string | null; size_bytes: number;
  question_count: number; paper_count: number; years: string; starter: number; installed_at: string;
}

const toMeta = (r: Row): PackMeta => ({ ...r, years: JSON.parse(r.years), starter: r.starter === 1 });

export class PackManager {
  private cache = new Map<string, { version: number; pack: Pack }>();

  constructor(
    private readonly db: Db,
    private readonly files: PackFiles,
    private readonly downloader: Downloader,
    private readonly sha256: Sha256,
    private readonly api: Api,
    private readonly now: () => Date = () => new Date(),
  ) {}

  async installed(): Promise<PackMeta[]> {
    return (await this.db.getAllAsync<Row>('SELECT * FROM packs ORDER BY exam_slug, subject_slug')).map(toMeta);
  }

  async meta(exam: string, subject: string): Promise<PackMeta | null> {
    const r = await this.db.getFirstAsync<Row>('SELECT * FROM packs WHERE exam_slug = ? AND subject_slug = ?', [exam, subject]);
    return r ? toMeta(r) : null;
  }

  /** Space the downloaded packs take on the phone (the starter pack is counted too). */
  async usedBytes(): Promise<number> {
    const r = await this.db.getFirstAsync<{ n: number | null }>('SELECT SUM(size_bytes) AS n FROM packs');
    return r?.n ?? 0;
  }

  /** Downloads the current pack for a subject, checks it against the catalog, and installs it. */
  async download(examSlug: string, subject: CatalogSubject, onProgress: (done: number, total: number) => void = () => {}, signal?: AbortSignal): Promise<PackMeta> {
    const entry = subject.pack;
    if (!entry) throw new PackError('There is no pack for this subject yet.', 'download');

    let bytes: Uint8Array;
    try {
      bytes = await this.downloader.download(this.api.url(entry.url), this.api.headers(), onProgress, signal);
    } catch (e) {
      if (signal?.aborted) throw new PackError('Download cancelled.', 'cancelled');
      throw new PackError('The download failed. Check your connection and try again.', 'download');
    }

    // A truncated or tampered file must never be installed.
    if ((await this.sha256(bytes)) !== entry.sha256.toLowerCase()) {
      throw new PackError('The download was damaged. Please try again.', 'checksum');
    }

    let text: string;
    try {
      text = strFromU8(gunzipSync(bytes));
    } catch {
      throw new PackError('The downloaded file could not be opened.', 'format');
    }

    return this.installFromText(text, { sha256: entry.sha256, size: bytes.length, starter: false, expectExam: examSlug, expectSubject: subject.slug });
  }

  /** Installs already-unzipped pack JSON: used for downloads and for the pack bundled inside the app. */
  async installFromText(text: string, o: { sha256?: string | null; size?: number; starter?: boolean; expectExam?: string; expectSubject?: string } = {}): Promise<PackMeta> {
    let pack: Pack;
    try {
      pack = JSON.parse(text) as Pack;
    } catch {
      throw new PackError('The pack file is not valid.', 'format');
    }

    if (typeof pack.format === 'number' && pack.format > SUPPORTED_FORMAT) {
      throw new PackError('This content needs a newer version of the app. Please update TestaCBT.', 'newer-app-needed');
    }
    if (pack.format !== SUPPORTED_FORMAT || !Array.isArray(pack.papers) || !pack.exam?.slug || !pack.subject?.slug) {
      throw new PackError('The pack file is not valid.', 'format');
    }
    if ((o.expectExam && pack.exam.slug !== o.expectExam) || (o.expectSubject && pack.subject.slug !== o.expectSubject)) {
      throw new PackError('The pack does not match the subject you chose.', 'format');
    }

    let questions = 0;
    const years = new Set<number>();
    for (const paper of pack.papers) {
      years.add(paper.year);
      questions += paper.items.filter((i) => i.type === 'mcq').length;
    }

    await this.files.write(pack.exam.slug, pack.subject.slug, text);

    const meta: PackMeta = {
      exam_slug: pack.exam.slug, subject_slug: pack.subject.slug, exam_name: pack.exam.name, subject_name: pack.subject.name,
      display_name: pack.subject.display_name || pack.subject.name, tier: o.starter || pack.tier === 'free' ? 'free' : 'full', version: pack.version, sha256: o.sha256 ?? null,
      size_bytes: o.size ?? text.length, question_count: questions, paper_count: pack.papers.length,
      years: [...years].sort((a, b) => b - a), starter: !!o.starter, installed_at: this.now().toISOString(),
    };

    await this.db.runAsync(
      `INSERT OR REPLACE INTO packs (exam_slug, subject_slug, exam_name, subject_name, display_name, tier, version, sha256, size_bytes, question_count, paper_count, years, starter, installed_at)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)`,
      [meta.exam_slug, meta.subject_slug, meta.exam_name, meta.subject_name, meta.display_name, meta.tier, meta.version, meta.sha256, meta.size_bytes, meta.question_count, meta.paper_count, JSON.stringify(meta.years), meta.starter ? 1 : 0, meta.installed_at],
    );

    // Answers restored from the server know their topic id but not its name; the pack knows the names.
    for (const t of pack.topics ?? []) {
      await this.db.runAsync('UPDATE attempts SET topic_name = ? WHERE topic_id = ? AND topic_name IS NULL', [t.name, t.id]);
    }

    this.cache.delete(`${meta.exam_slug}/${meta.subject_slug}`);
    return meta;
  }

  /** Loads an installed pack into memory (the two most recent stay cached). */
  async load(examSlug: string, subjectSlug: string): Promise<Pack | null> {
    const meta = await this.meta(examSlug, subjectSlug);
    if (!meta) return null;

    const key = `${examSlug}/${subjectSlug}`;
    const hit = this.cache.get(key);
    if (hit && hit.version === meta.version) return hit.pack;

    const text = await this.files.read(examSlug, subjectSlug);
    if (!text) return null;

    const pack = JSON.parse(text) as Pack;
    this.cache.set(key, { version: meta.version, pack });
    while (this.cache.size > 2) this.cache.delete(this.cache.keys().next().value as string);
    return pack;
  }

  async remove(examSlug: string, subjectSlug: string): Promise<void> {
    await this.files.remove(examSlug, subjectSlug);
    await this.db.runAsync('DELETE FROM packs WHERE exam_slug = ? AND subject_slug = ?', [examSlug, subjectSlug]);
    this.cache.delete(`${examSlug}/${subjectSlug}`);
  }

  /**
   * Subjects whose catalog pack should replace the installed one: it is newer, it is the other tier (a purchase
   * unlocked the subject, or ran out), or the installed one is the bundled starter (always out of date).
   */
  async updatesAvailable(examSlug: string, subjects: CatalogSubject[]): Promise<CatalogSubject[]> {
    const out: CatalogSubject[] = [];
    for (const s of subjects) {
      const m = await this.meta(examSlug, s.slug);
      if (m && s.pack && needsReplacing(m, s.pack)) out.push(s);
    }
    return out;
  }
}
