import { kvGet, kvSet } from '@/db/progress';
import type { Db } from '@/db/types';
import { Api } from './api';
import type { Catalog, CatalogExam, CatalogSubject } from '@/core/types';

const BODY_KEY = 'catalog.body';
const ETAG_KEY = 'catalog.etag';

export async function cachedCatalog(db: Db): Promise<Catalog | null> {
  const raw = await kvGet(db, BODY_KEY);
  if (!raw) return null;
  try { return JSON.parse(raw) as Catalog; } catch { return null; }
}

/**
 * Asks the server for the catalog. When nothing changed it answers 304 with no body, so a check costs almost
 * no data. Returns the fresh catalog (or the cached one when unchanged).
 */
export async function refreshCatalog(db: Db, api: Api): Promise<{ catalog: Catalog; changed: boolean }> {
  const cached = await cachedCatalog(db);
  const etag = cached ? await kvGet(db, ETAG_KEY) : null;

  const res = await api.get<Catalog>('/catalog', { etag });
  if (res.status === 304 && cached) return { catalog: cached, changed: false };

  await kvSet(db, BODY_KEY, JSON.stringify(res.data));
  if (res.etag) await kvSet(db, ETAG_KEY, res.etag);
  return { catalog: res.data, changed: true };
}

/** Lookups by slug or id, so an answer can be filed under the right exam and subject. */
export class CatalogIndex {
  private exams = new Map<string, CatalogExam>();
  private examsById = new Map<number, CatalogExam>();
  private subjects = new Map<string, CatalogSubject>();
  private subjectsById = new Map<number, { exam: CatalogExam; subject: CatalogSubject }>();

  constructor(public readonly catalog: Catalog | null) {
    for (const e of catalog?.exams ?? []) {
      this.exams.set(e.slug, e);
      this.examsById.set(e.id, e);
      for (const s of e.subjects) {
        this.subjects.set(`${e.slug}/${s.slug}`, s);
        if (!this.subjectsById.has(s.id)) this.subjectsById.set(s.id, { exam: e, subject: s });
      }
    }
  }

  exam(slug: string) { return this.exams.get(slug) ?? null; }
  examById(id: number) { return this.examsById.get(id) ?? null; }
  subject(examSlug: string, subjectSlug: string) { return this.subjects.get(`${examSlug}/${subjectSlug}`) ?? null; }
  subjectById(id: number) { return this.subjectsById.get(id)?.subject ?? null; }
  examOfSubject(id: number) { return this.subjectsById.get(id)?.exam ?? null; }

  /** The ids and names an attempt row needs. */
  locate(examSlug: string, subjectSlug: string) {
    const exam = this.exam(examSlug);
    const subject = this.subject(examSlug, subjectSlug);
    return {
      examId: exam?.id ?? null,
      subjectId: subject?.id ?? null,
      subjectName: subject?.display_name ?? subject?.name ?? null,
    };
  }
}
