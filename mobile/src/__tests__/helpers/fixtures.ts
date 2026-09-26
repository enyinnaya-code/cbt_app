import { gzipSync, strToU8 } from 'fflate';
import type { Catalog, CatalogExam, CatalogSubject, MockFormat, Pack, PackItem } from '@/core/types';

export function mcq(id: number, over: Partial<Extract<PackItem, { type: 'mcq' }>> = {}): PackItem {
  return {
    id, type: 'mcq', html: `<p>Question ${id}</p>`, options: { A: 'one', B: 'two', C: 'three', D: 'four' }, answer: 'B', marks: 1,
    topic_id: null, explanation_en: null, explanation_pcm: null, ...over,
  };
}

export function instruction(id: number, html = '<p>Read the passage.</p>'): PackItem {
  return { id, type: 'instruction', html };
}

export function makePack(o: { exam?: string; subject?: string; display?: string; version?: number; papers: { id: number; year: number; items: PackItem[] }[]; topics?: { id: number; name: string }[] }): Pack {
  const exam = o.exam ?? 'jamb';
  const subject = o.subject ?? 'physics';
  return {
    format: 1, version: o.version ?? 1, generated_at: '2026-09-26T12:00:00+01:00',
    exam: { slug: exam, name: exam.toUpperCase() },
    subject: { slug: subject, name: subject, display_name: o.display ?? subject },
    topics: o.topics ?? [],
    papers: o.papers.map((p) => ({ ...p, title: null, duration_minutes: null })),
  };
}

/** A pack with `n` plain questions in one paper, ids starting at `startId`. */
export function simplePack(exam: string, subject: string, n: number, startId = 1, display?: string): Pack {
  return makePack({ exam, subject, display, papers: [{ id: 1, year: 2020, items: Array.from({ length: n }, (_, i) => mcq(startId + i)) }] });
}

export const JAMB_FORMAT: MockFormat = {
  label: 'JAMB UTME', subject_count: 4, compulsory: 'english-language',
  questions: { 'english-language': 60, default: 40 }, minutes: 120, score_max: 400,
};

export const WAEC_FORMAT: MockFormat = { label: 'WAEC objective', subject_count: 1, compulsory: null, questions: { default: 50 }, minutes: 60, score_max: 100 };

export function subject(id: number, slug: string, over: Partial<CatalogSubject> = {}): CatalogSubject {
  return { id, slug, name: slug, display_name: slug, code: slug.slice(0, 2), pack: null, ...over };
}

export function catalogOf(exams: CatalogExam[]): Catalog {
  return { exams, mock: { jamb: JAMB_FORMAT, waec: WAEC_FORMAT }, practice_counts: [10, 20, 40, 50], strong_accuracy: 70 };
}

export const gz = (pack: Pack | string): Uint8Array => gzipSync(strToU8(typeof pack === 'string' ? pack : JSON.stringify(pack)));

/** A plausible fetch Response. */
export function res(status: number, body: unknown = null, headers: Record<string, string> = {}): Response {
  return new Response(body === null ? null : typeof body === 'string' ? body : JSON.stringify(body), { status, headers });
}
