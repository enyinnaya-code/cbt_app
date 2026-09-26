import { buildMock, plannedQuestions, scoreMock, validateChoice, type MockPaper } from '@/core/mock';
import { seededRng } from '@/core/selector';
import { JAMB_FORMAT, WAEC_FORMAT, mcq, makePack, simplePack } from './helpers/fixtures';

const chosen = (n = 50) => [
  { subjectId: 4, pack: simplePack('jamb', 'physics', n, 3000) },
  { subjectId: 1, pack: simplePack('jamb', 'english-language', n, 1000, 'Use of English') },
  { subjectId: 3, pack: simplePack('jamb', 'mathematics', n, 2000) },
  { subjectId: 2, pack: simplePack('jamb', 'chemistry', n, 4000) },
];

describe('validateChoice', () => {
  it('needs the right number of subjects, with English for JAMB', () => {
    expect(validateChoice(JAMB_FORMAT, ['english-language', 'physics', 'chemistry', 'mathematics'])).toBeNull();
    expect(validateChoice(JAMB_FORMAT, ['english-language', 'physics'])).toMatch('exactly 4');
    expect(validateChoice(JAMB_FORMAT, ['physics', 'chemistry', 'mathematics', 'biology'])).toMatch('compulsory');
    expect(validateChoice(WAEC_FORMAT, ['physics'])).toBeNull();
    expect(validateChoice(WAEC_FORMAT, ['physics', 'chemistry'])).toBe('Choose one subject.');
    expect(validateChoice(WAEC_FORMAT, [])).toBe('Choose one subject.');
  });
});

describe('buildMock', () => {
  it('orders subjects with English first, then A to Z, and uses the real question counts', () => {
    const paper = buildMock(JAMB_FORMAT, chosen(80), seededRng(7));

    expect(paper.groups.map((g) => g.name)).toEqual(['Use of English', 'chemistry', 'mathematics', 'physics']);
    expect(paper.groups.map((g) => g.count)).toEqual([60, 40, 40, 40]);
    expect(paper.questions).toHaveLength(180);
    expect(paper.minutes).toBe(120);
    expect(new Set(paper.questions.map((q) => q.id)).size).toBe(180);
  });

  it('keeps each subject\'s questions together and in group order', () => {
    const paper = buildMock(JAMB_FORMAT, chosen(80), seededRng(7));
    let offset = 0;
    for (const g of paper.groups) {
      const slice = paper.questions.slice(offset, offset + g.count);
      expect(new Set(slice.map((q) => q.subjectSlug)).size).toBe(1);
      offset += g.count;
    }
  });

  it('shortens the clock in proportion when there are fewer questions than the real exam', () => {
    const paper = buildMock(JAMB_FORMAT, chosen(10), seededRng(1));   // 40 of 180 questions
    expect(paper.questions).toHaveLength(40);
    expect(paper.minutes).toBe(Math.ceil((120 * 40) / 180));
  });

  it('never goes below five minutes', () => {
    const tiny = buildMock(WAEC_FORMAT, [{ subjectId: 1, pack: simplePack('waec', 'physics', 5) }], seededRng(1));
    expect(tiny.minutes).toBe(Math.ceil((60 * 5) / 50));
    expect(tiny.minutes).toBeGreaterThanOrEqual(5);
  });

  it('uses the real WAEC count for a single subject', () => {
    const paper = buildMock(WAEC_FORMAT, [{ subjectId: 1, pack: simplePack('waec', 'physics', 80) }], seededRng(1));
    expect(paper.questions).toHaveLength(50);
    expect(paper.minutes).toBe(60);
  });

  it('carries passages with their questions', () => {
    const pack = makePack({
      exam: 'waec', subject: 'physics',
      papers: [
        { id: 1, year: 2020, items: [{ id: 900, type: 'instruction', html: '<p>Story</p>' }, mcq(1), mcq(2)] },
        { id: 2, year: 2021, items: Array.from({ length: 10 }, (_, i) => mcq(10 + i)) },
      ],
    });
    const paper = buildMock(WAEC_FORMAT, [{ subjectId: 1, pack }], seededRng(2));

    const withPassage = paper.questions.filter((q) => q.passageId === 900);
    expect(withPassage.map((q) => q.id).sort((a, b) => a - b)).toEqual([1, 2]);
    expect(paper.passages[900]).toBe('<p>Story</p>');
  });

  it('plannedQuestions falls back to the default', () => {
    expect(plannedQuestions(JAMB_FORMAT, 'english-language')).toBe(60);
    expect(plannedQuestions(JAMB_FORMAT, 'biology')).toBe(40);
  });
});

describe('scoreMock', () => {
  const paper = (): MockPaper => buildMock(JAMB_FORMAT, chosen(80), seededRng(3));

  it('marks each subject out of 100 and adds them up to 400', () => {
    const p = paper();
    const answers: Record<number, string> = {};
    // Everything right in English (60), half right in chemistry (20 of 40), one wrong in maths, nothing in physics.
    p.questions.slice(0, 60).forEach((q) => (answers[q.id] = q.answer));
    p.questions.slice(60, 80).forEach((q) => (answers[q.id] = q.answer));
    answers[p.questions[100].id] = 'A';   // wrong (the key is B in the fixtures)

    const s = scoreMock(p, answers);

    expect(s.groups.map((g) => g.percent)).toEqual([100, 50, 0, 0]);
    expect(s.score).toBe(150);
    expect(s.total).toBe(400);
    expect(s.correct).toBe(80);
    expect(s.answered).toBe(81);
    expect(s.questions).toBe(180);
  });

  it('accepts lower case letters and ignores answers to other questions', () => {
    const p = paper();
    const first = p.questions[0];
    const s = scoreMock(p, { [first.id]: first.answer.toLowerCase(), 999999: 'B' });
    expect(s.correct).toBe(1);
    expect(s.answered).toBe(1);
  });

  it('rounds each subject\'s percentage', () => {
    const p = buildMock(WAEC_FORMAT, [{ subjectId: 1, pack: simplePack('waec', 'physics', 3) }], seededRng(1));
    const answers: Record<number, string> = { [p.questions[0].id]: p.questions[0].answer };
    expect(scoreMock(p, answers).score).toBe(33);   // 1 of 3
  });

  it('tallies topics for the weak-topics list', () => {
    const pack = makePack({ exam: 'waec', subject: 'physics', topics: [{ id: 7, name: 'Heat' }], papers: [{ id: 1, year: 2020, items: [mcq(1, { topic_id: 7 }), mcq(2, { topic_id: 7 }), mcq(3)] }] });
    const p = buildMock(WAEC_FORMAT, [{ subjectId: 1, pack }], seededRng(1));
    const right = p.questions.find((q) => q.topicId === 7)!;

    const s = scoreMock(p, { [right.id]: right.answer });

    expect(s.topics).toEqual([{ topicId: 7, name: 'Heat', correct: 1, total: 2 }]);
  });
});
