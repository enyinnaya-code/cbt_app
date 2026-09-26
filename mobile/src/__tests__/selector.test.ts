import { PASSAGE_MAX_QUESTIONS, candidates, choose, practiceSession, savedSession, seededRng, summarise, toSession } from '@/core/selector';
import { instruction, makePack, mcq } from './helpers/fixtures';

describe('candidates', () => {
  it('offers only questions with a usable answer key', () => {
    const pack = makePack({
      papers: [{
        id: 1, year: 2020, items: [
          mcq(1),
          mcq(2, { answer: '' }),                          // no key
          mcq(3, { answer: 'E' }),                         // key is not an option
          mcq(4, { options: { A: 'only one' }, answer: 'A' }), // fewer than two options
          mcq(5, { answer: 'b' }),                         // lowercase key is fine
          mcq(6, { options: { A: 'x', B: '', C: 'z' }, answer: 'B' }), // key option is empty
        ],
      }],
    });

    expect(candidates(pack).map((c) => c.q.id)).toEqual([1, 5]);
  });

  it('keeps paper order and filters by year, topic and ids', () => {
    const pack = makePack({
      papers: [
        { id: 1, year: 2019, items: [mcq(1, { topic_id: 7 }), mcq(2), mcq(3, { topic_id: 7 })] },
        { id: 2, year: 2020, items: [mcq(4), mcq(5, { topic_id: 7 })] },
      ],
    });

    expect(candidates(pack).map((c) => c.q.id)).toEqual([1, 2, 3, 4, 5]);
    expect(candidates(pack, { year: 2020 }).map((c) => c.q.id)).toEqual([4, 5]);
    expect(candidates(pack, { topicId: 7 }).map((c) => c.q.id)).toEqual([1, 3, 5]);
    expect(candidates(pack, { onlyIds: new Set([2, 5]) }).map((c) => c.q.id)).toEqual([2, 5]);
  });

  it('attaches a passage to the questions that follow it', () => {
    const pack = makePack({ papers: [{ id: 1, year: 2020, items: [instruction(10), mcq(1), mcq(2), instruction(11), mcq(3)] }] });

    expect(candidates(pack).map((c) => [c.q.id, c.passageId])).toEqual([[1, 10], [2, 10], [3, 11]]);
  });

  it('treats an instruction with many questions as general directions, not a passage', () => {
    const many = Array.from({ length: PASSAGE_MAX_QUESTIONS + 1 }, (_, i) => mcq(i + 1));
    const pack = makePack({ papers: [{ id: 1, year: 2020, items: [instruction(100, '<p>Answer all questions.</p>'), ...many] }] });

    expect(new Set(candidates(pack).map((c) => c.passageId))).toEqual(new Set([null]));
    expect(toSession(pack, candidates(pack)).passages).toEqual({});
  });

  it('does not let a passage leak into the next paper', () => {
    const pack = makePack({
      papers: [
        { id: 1, year: 2019, items: [instruction(10), mcq(1)] },
        { id: 2, year: 2020, items: [mcq(2)] },
      ],
    });

    expect(candidates(pack).map((c) => [c.q.id, c.passageId])).toEqual([[1, 10], [2, null]]);
  });
});

describe('choose', () => {
  const pack = makePack({
    papers: [
      { id: 1, year: 2018, items: [instruction(10), mcq(1), mcq(2), mcq(3)] },
      { id: 2, year: 2019, items: Array.from({ length: 12 }, (_, i) => mcq(20 + i)) },
      { id: 3, year: 2020, items: Array.from({ length: 12 }, (_, i) => mcq(100 + i)) },
    ],
  });

  it('keeps order when asked', () => {
    const picked = choose(candidates(pack, { year: 2020 }), 5, true, seededRng(1));
    expect(picked.map((c) => c.q.id)).toEqual([100, 101, 102, 103, 104]);
  });

  it('shuffles, but never splits a passage group', () => {
    for (let seed = 1; seed <= 25; seed++) {
      const ids = choose(candidates(pack), 100, false, seededRng(seed)).map((c) => c.q.id);
      const at = [1, 2, 3].map((id) => ids.indexOf(id));
      expect(at).toEqual([Math.min(...at), Math.min(...at) + 1, Math.min(...at) + 2]);
    }
  });

  it('is repeatable with the same seed and differs between seeds', () => {
    const a = choose(candidates(pack), 100, false, seededRng(5)).map((c) => c.q.id);
    const b = choose(candidates(pack), 100, false, seededRng(5)).map((c) => c.q.id);
    const c = choose(candidates(pack), 100, false, seededRng(6)).map((c2) => c2.q.id);
    expect(a).toEqual(b);
    expect(a).not.toEqual(c);
  });

  it('respects the count and never returns fewer than one', () => {
    expect(choose(candidates(pack), 4, false, seededRng(1))).toHaveLength(4);
    expect(choose(candidates(pack), 0, false, seededRng(1))).toHaveLength(1);
    expect(choose(candidates(pack), 9999, false, seededRng(1))).toHaveLength(27);
  });
});

describe('sessions', () => {
  const pack = makePack({
    topics: [{ id: 7, name: 'Motion' }],
    papers: [{ id: 1, year: 2020, items: [instruction(10, '<p>Story</p>'), mcq(1, { topic_id: 7, explanation_en: 'Because.', marks: 3 }), mcq(2)] }],
  });

  it('prepares questions with their passage, topic name, year and where they came from', () => {
    const s = practiceSession(pack, { year: 2020, count: 5 });

    expect(s.questions).toHaveLength(2);
    expect(s.questions[0]).toMatchObject({ id: 1, answer: 'B', marks: 3, topic: 'Motion', year: 2020, passageId: 10, explanationEn: 'Because.', examSlug: 'jamb', subjectSlug: 'physics' });
    expect(s.passages).toEqual({ 10: '<p>Story</p>' });
  });

  it('practises one topic', () => {
    expect(practiceSession(pack, { topicId: 7, count: 5 }).questions.map((q) => q.id)).toEqual([1]);
  });

  it('builds a saved session from bookmarks across packs', () => {
    const other = makePack({ exam: 'waec', subject: 'chemistry', papers: [{ id: 2, year: 2018, items: [mcq(50), mcq(51)] }] });
    const s = savedSession([pack, other], new Set([2, 51, 999]), 10, seededRng(3));

    expect(s.questions.map((q) => q.id).sort((a, b) => a - b)).toEqual([2, 51]);
    expect(new Set(s.questions.map((q) => q.subjectSlug))).toEqual(new Set(['physics', 'chemistry']));
  });

  it('summarises what is on the phone', () => {
    const many = makePack({ papers: [{ id: 1, year: 2019, items: [mcq(1), instruction(2), mcq(3)] }, { id: 2, year: 2021, items: [mcq(4)] }] });
    expect(summarise(many)).toEqual({ questions: 3, years: [2021, 2019] });
  });
});
