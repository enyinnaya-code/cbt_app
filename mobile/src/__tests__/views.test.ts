import { buildViews, codeOf, formatFor, needsReplacing, type InstalledInfo } from '@/core/views';
import { catalogOf, subject } from './helpers/fixtures';

const inst = (exam: string, sub: string, over: Partial<InstalledInfo> = {}): InstalledInfo => ({
  exam_slug: exam, subject_slug: sub, exam_name: exam.toUpperCase(), subject_name: sub, display_name: sub, tier: 'full', version: 1, size_bytes: 500, question_count: 40, years: [2020], starter: false, ...over,
});

const pack = (version: number, bytes = 9000) => ({ version, size_bytes: bytes, sha256: 'a', paper_count: 1, question_count: 40, years: [2020], built_at: '', url: '/x' });

describe('buildViews', () => {
  it('combines the catalog with what is installed', () => {
    const catalog = catalogOf([{ id: 3, slug: 'jamb', name: 'JAMB', subjects: [subject(1, 'english-language', { display_name: 'Use of English', code: 'En', pack: pack(2) }), subject(4, 'physics', { code: 'Ph', pack: pack(1, 5000) })] }]);

    const [jamb] = buildViews(catalog, [inst('jamb', 'english-language', { version: 1 })]);

    expect(jamb).toMatchObject({ slug: 'jamb', examId: 3 });
    expect(jamb.subjects[0]).toMatchObject({ name: 'Use of English', code: 'En', subjectId: 1, updateAvailable: true, downloadBytes: 9000 });
    expect(jamb.subjects[0].installed?.version).toBe(1);
    expect(jamb.subjects[1]).toMatchObject({ installed: null, updateAvailable: false, downloadBytes: 5000 });
  });

  it('works with no catalog at all, from the packs on the phone', () => {
    const views = buildViews(null, [
      inst('jamb', 'english-language', { display_name: 'Use of English', starter: true }),
      inst('jamb', 'physics'),
      inst('waec', 'chemistry', { exam_name: 'WAEC' }),
    ]);

    expect(views.map((v) => [v.slug, v.subjects.map((s) => s.name)])).toEqual([['jamb', ['Use of English', 'physics']], ['waec', ['chemistry']]]);
    expect(views[0].subjects[0]).toMatchObject({ code: 'UE', subjectId: null, offered: null, updateAvailable: false });
  });

  it('a starter pack is always offered a replacement', () => {
    const catalog = catalogOf([{ id: 3, slug: 'jamb', name: 'JAMB', subjects: [subject(4, 'physics', { pack: pack(1) })] }]);
    const [jamb] = buildViews(catalog, [inst('jamb', 'physics', { version: 0, starter: true })]);
    expect(jamb.subjects[0].updateAvailable).toBe(true);
  });

  it('a subject the student has not unlocked is locked, with what unlocking gives', () => {
    const catalog = catalogOf([{ id: 3, slug: 'jamb', name: 'JAMB', subjects: [
      subject(4, 'physics', { access: 'free', price: 1500, free_questions: 30, full_question_count: 800, expires_at: null, pack: { ...pack(1), tier: 'free' } }),
      subject(5, 'chemistry', { access: 'full', price: 1000, expires_at: '2027-01-05T10:00:00+01:00', pack: { ...pack(1), tier: 'full' } }),
    ] }]);

    const [jamb] = buildViews(catalog, []);

    expect(jamb.subjects[0]).toMatchObject({ locked: true, price: 1500, freeQuestions: 30, fullQuestions: 800, expiresAt: null });
    expect(jamb.subjects[1]).toMatchObject({ locked: false, expiresAt: '2027-01-05T10:00:00+01:00' });
  });

  it('with no catalog, a downloaded sample is locked but the bundled starter questions are not', () => {
    const views = buildViews(null, [inst('jamb', 'physics', { tier: 'free' }), inst('jamb', 'biology', { tier: 'free', starter: true }), inst('jamb', 'chemistry')]);

    expect(views[0].subjects.map((s) => s.locked)).toEqual([true, false, false]);
  });

  it('paying replaces the sample with the full pack, and an expired purchase goes back to the sample', () => {
    const catalog = (tier: 'full' | 'free') => catalogOf([{ id: 3, slug: 'jamb', name: 'JAMB', subjects: [subject(4, 'physics', { access: tier, pack: { ...pack(1), tier } })] }]);

    expect(buildViews(catalog('full'), [inst('jamb', 'physics', { tier: 'free', version: 9 })])[0].subjects[0].updateAvailable).toBe(true);
    expect(buildViews(catalog('free'), [inst('jamb', 'physics', { tier: 'full', version: 9 })])[0].subjects[0].updateAvailable).toBe(true);
    expect(buildViews(catalog('full'), [inst('jamb', 'physics', { tier: 'full', version: 1 })])[0].subjects[0].updateAvailable).toBe(false);
  });

  it('shows packs the catalog no longer lists, so a downloaded subject never disappears', () => {
    const catalog = catalogOf([{ id: 3, slug: 'jamb', name: 'JAMB', subjects: [subject(4, 'physics')] }]);
    const [jamb] = buildViews(catalog, [inst('jamb', 'biology')]);
    expect(jamb.subjects.map((s) => s.slug)).toEqual(['physics', 'biology']);
  });

  it('codes are two letters', () => {
    expect(codeOf('Use of English')).toBe('UE');
    expect(codeOf('Physics')).toBe('Ph');
    expect(codeOf('Christian Religious Studies')).toBe('CR');
    expect(codeOf('x', 'Zz')).toBe('Zz');
  });
});

describe('needsReplacing', () => {
  const have = { tier: 'full' as const, version: 3, starter: false };

  it('replaces on a newer version of the same tier, never on an older or equal one', () => {
    expect(needsReplacing(have, { tier: 'full', version: 4 })).toBe(true);
    expect(needsReplacing(have, { tier: 'full', version: 3 })).toBe(false);
    expect(needsReplacing(have, { tier: 'full', version: 2 })).toBe(false);
  });

  it('a different tier always replaces, whatever the version numbers say', () => {
    expect(needsReplacing(have, { tier: 'free', version: 1 })).toBe(true);
    expect(needsReplacing({ ...have, tier: 'free' }, { tier: 'full', version: 1 })).toBe(true);
  });

  it('an offered pack with no tier is a full pack, and the starter is always replaced', () => {
    expect(needsReplacing(have, { version: 3 })).toBe(false);
    expect(needsReplacing({ ...have, tier: 'free' }, { version: 1 })).toBe(true);
    expect(needsReplacing({ tier: 'free', version: 0, starter: true }, { tier: 'free', version: 0 })).toBe(true);
  });
});

describe('formatFor', () => {
  it('prefers the server, then the built-in formats, then a sensible single-subject default', () => {
    expect(formatFor(catalogOf([]), 'jamb', 'JAMB').minutes).toBe(120);
    expect(formatFor(null, 'jamb', 'JAMB')).toMatchObject({ subject_count: 4, compulsory: 'english-language' });
    expect(formatFor(null, 'neco', 'NECO').minutes).toBe(60);
    expect(formatFor(null, 'post-utme', 'Post-UTME')).toMatchObject({ subject_count: 1, questions: { default: 40 } });
    expect(formatFor(null, 'igcse', 'IGCSE').label).toBe('IGCSE multiple choice');
    expect(formatFor(null, 'gce', 'GCE')).toMatchObject({ label: 'GCE mock', subject_count: 1 });

    const custom = catalogOf([]);
    custom.mock.jamb = { ...custom.mock.jamb, minutes: 90 };
    expect(formatFor(custom, 'jamb', 'JAMB').minutes).toBe(90);
  });
});
