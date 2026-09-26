/** Shapes shared across the app. They mirror the server's pack format (docs/API.md, "Pack format"). */

export type Letter = 'A' | 'B' | 'C' | 'D' | 'E';
export const LETTERS: Letter[] = ['A', 'B', 'C', 'D', 'E'];

export interface PackMcq {
  id: number;
  type: 'mcq';
  html: string;
  options: Record<string, string>;
  answer: string;
  marks: number;
  topic_id: number | null;
  explanation_en: string | null;
  explanation_pcm: string | null;
}

export interface PackInstruction {
  id: number;
  type: 'instruction';
  html: string;
}

export type PackItem = PackMcq | PackInstruction;

export interface PackPaper {
  id: number;
  year: number;
  title: string | null;
  duration_minutes: number | null;
  items: PackItem[];
}

export interface Pack {
  format: number;
  version: number;
  generated_at: string;
  exam: { slug: string; name: string };
  subject: { slug: string; name: string; display_name: string };
  topics: { id: number; name: string }[];
  papers: PackPaper[];
}

/** One question prepared for a practice or mock session. */
export interface SessionQuestion {
  id: number;
  html: string;
  options: Record<string, string>;
  answer: string;
  marks: number;
  topicId: number | null;
  topic: string | null;
  year: number;
  passageId: number | null;
  explanationEn: string | null;
  explanationPcm: string | null;
  // Where it came from, so an answer can be filed under the right exam and subject.
  examSlug: string;
  subjectSlug: string;
}

export interface SessionData {
  questions: SessionQuestion[];
  passages: Record<number, string>;
}

// ---- catalog (GET /catalog) ----

export interface CatalogPack {
  version: number;
  size_bytes: number;
  sha256: string;
  paper_count: number;
  question_count: number;
  years: number[];
  built_at: string;
  url: string;
}

export interface CatalogSubject {
  id: number;
  slug: string;
  name: string;
  display_name: string;
  code: string;
  pack: CatalogPack | null;
}

export interface CatalogExam {
  id: number;
  slug: string;
  name: string;
  subjects: CatalogSubject[];
}

export interface MockFormat {
  label: string;
  subject_count: number;
  compulsory: string | null;
  questions: Record<string, number>;
  minutes: number;
  score_max: number;
}

export interface Catalog {
  exams: CatalogExam[];
  mock: Record<string, MockFormat>;
  practice_counts: number[];
  strong_accuracy: number;
}

export interface User {
  id: number;
  name: string;
  email: string;
  role: string;
  avatar_url: string | null;
  preferred_exams: string[];
  has_password: boolean;
}
