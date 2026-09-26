import { router, useLocalSearchParams } from 'expo-router';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Alert, ScrollView, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { practiceSession, savedSession } from '@/core/selector';
import type { Pack, SessionData } from '@/core/types';
import { bookmarkedIds, bookmarks } from '@/db/progress';
import { recordAnswer, toggleBookmark } from '@/services/recorder';
import { useData } from '@/state/data';
import { services } from '@/state/services';
import { useSettings } from '@/state/settings';
import { Badge, Button, Card, Empty, IconButton, ProgressBar, Row, Screen, T } from '@/ui/components';
import { Icon } from '@/ui/Icon';
import { AnswerSheet, Explanation, QuestionView, Verdict, type SheetState } from '@/ui/QuestionView';
import { useTheme } from '@/ui/theme';

type Params = { exam?: string; subject?: string; year?: string; topic?: string; mode?: string; count?: string; saved?: string };

export default function PracticeSession() {
  const p = useLocalSearchParams<Params>();
  const [session, setSession] = useState<SessionData | null>(null);
  const [failed, setFailed] = useState<string | null>(null);
  const instant = p.mode !== 'end';

  useEffect(() => {
    let live = true;
    (async () => {
      const { packs, db } = services();
      const count = Math.max(1, Number(p.count ?? 20));

      let built: SessionData;
      if (p.saved === '1') {
        const marks = await bookmarks(db);
        const ids = new Set(marks.map((m) => m.question_id));
        const need = new Map<string, true>();
        marks.forEach((m) => { if (m.exam_slug && m.subject_slug) need.set(`${m.exam_slug}/${m.subject_slug}`, true); });
        const loaded: Pack[] = [];
        for (const key of need.keys()) { const [e, s] = key.split('/'); const pk = await packs.load(e, s); if (pk) loaded.push(pk); }
        built = savedSession(loaded, ids, count);
      } else {
        const pack = await packs.load(p.exam ?? '', p.subject ?? '');
        if (!pack) throw new Error('These questions are not on your phone. Download the subject first.');
        built = practiceSession(pack, { year: p.year ? Number(p.year) : null, topicId: p.topic ? Number(p.topic) : null, count });
      }

      if (!built.questions.length) throw new Error('There are no questions for that choice. Try another year or topic.');
      if (live) setSession(built);
    })().catch((e) => { if (live) setFailed(e instanceof Error ? e.message : 'Could not start practice.'); });

    return () => { live = false; };
  }, [p.exam, p.subject, p.year, p.topic, p.mode, p.count, p.saved]);

  if (failed) {
    return (
      <Screen>
        <IconButton icon="x" label="Close" onPress={() => router.back()} />
        <Empty icon="book" title="Nothing to practise" body={failed} action={<Button label="Go back" full={false} onPress={() => router.back()} />} />
      </Screen>
    );
  }
  if (!session) return <Screen scroll={false}><View /></Screen>;

  return <Runner session={session} instant={instant} title={p.saved === '1' ? 'Saved questions' : undefined} exam={p.exam} year={p.year} />;
}

function Runner({ session, instant, title, exam, year }: { session: SessionData; instant: boolean; title?: string; exam?: string; year?: string }) {
  const { c } = useTheme();
  const insets = useSafeAreaInsets();
  const lang = useSettings((s) => s.lang);
  const qs = session.questions;

  const [i, setI] = useState(0);
  const [answers, setAnswers] = useState<Record<number, string>>({});
  const [finished, setFinished] = useState(false);
  const [review, setReview] = useState(false);
  const [saved, setSaved] = useState<Set<number>>(new Set());
  const shownAt = useRef<Record<number, number>>({});
  const scroller = useRef<ScrollView>(null);

  const q = qs[i];
  const reveal = review || (instant && answers[q?.id] !== undefined);

  useEffect(() => { bookmarkedIds(services().db).then(setSaved); }, []);
  useEffect(() => { if (q && !shownAt.current[q.id]) shownAt.current[q.id] = Date.now(); scroller.current?.scrollTo({ y: 0, animated: false }); }, [i, q]);

  const record = useCallback(async (question: (typeof qs)[number], letter: string) => {
    const { db, newUuid } = services();
    await recordAnswer(db, useData.getState().index, question, letter, 'practice', Date.now() - (shownAt.current[question.id] ?? Date.now()), newUuid());
    void useData.getState().refreshPending();
  }, []);

  const choose = (letter: string) => {
    if (finished || (!review && instant && answers[q.id] !== undefined)) return;
    setAnswers((a) => ({ ...a, [q.id]: letter }));
    if (instant) void record(q, letter);
  };

  const finish = async () => {
    const unanswered = qs.filter((x) => answers[x.id] === undefined).length;
    const done = async () => {
      if (!instant) for (const x of qs) if (answers[x.id] !== undefined) await record(x, answers[x.id]);
      setFinished(true);
      void useData.getState().syncNow();
    };
    if (unanswered) {
      Alert.alert('Finish now?', `You have ${unanswered} unanswered ${unanswered === 1 ? 'question' : 'questions'}.`, [{ text: 'Keep going', style: 'cancel' }, { text: 'Finish', onPress: () => void done() }]);
    } else {
      await done();
    }
  };

  const next = () => {
    if (review) { if (i < qs.length - 1) setI(i + 1); else { setReview(false); } return; }
    if (i < qs.length - 1) setI(i + 1); else void finish();
  };

  const leave = () => {
    const started = Object.keys(answers).length > 0;
    if (!finished && !instant && started) {
      Alert.alert('Leave practice?', 'Your answers have not been saved yet.', [{ text: 'Stay', style: 'cancel' }, { text: 'Leave', style: 'destructive', onPress: () => router.back() }]);
    } else {
      router.back();
    }
  };

  const flip = async () => {
    const on = !saved.has(q.id);
    setSaved((s) => { const n = new Set(s); on ? n.add(q.id) : n.delete(q.id); return n; });
    await toggleBookmark(services().db, useData.getState().index, q, on);
    void useData.getState().refreshPending();
  };

  const stateOf = (index: number): SheetState => {
    const a = answers[qs[index].id];
    if (a === undefined) return 'none';
    if (review || instant) return a === qs[index].answer ? 'right' : 'wrong';
    return 'answered';
  };

  const right = qs.filter((x) => answers[x.id] === x.answer).length;
  const answered = Object.keys(answers).length;

  if (finished && !review) {
    const pct = qs.length ? Math.round((right / qs.length) * 100) : 0;
    return (
      <Screen footer={<><Button label="Review answers" variant="outline" full={false} style={{ flex: 1 }} onPress={() => { setReview(true); setI(0); }} /><Button label="Done" full={false} style={{ flex: 1 }} onPress={() => router.back()} /></>}>
        <Row between><T variant="h2">Your result</T><IconButton icon="x" label="Close" onPress={() => router.back()} /></Row>
        <View style={{ alignItems: 'center', gap: 10, paddingVertical: 8 }}>
          <View accessible accessibilityLabel={`${right} out of ${qs.length} correct`} style={{ width: 170, height: 170, borderRadius: 85, borderWidth: 12, borderColor: pct >= 50 ? c.primary : c.accent, alignItems: 'center', justifyContent: 'center' }}>
            <T variant="display" style={{ fontSize: 44 }}>{right}</T>
            <T variant="small" muted>out of {qs.length}</T>
          </View>
          <Badge label={`${pct}% correct`} kind="green" icon="trophy" />
        </View>
        <Row style={{ gap: 8 }}>
          {[['Correct', right], ['Wrong', answered - right], ['Skipped', qs.length - answered]].map(([label, n]) => (
            <Card key={label as string} style={{ flex: 1, gap: 4 }}><T variant="tiny" muted>{label}</T><T variant="h3">{n}</T></Card>
          ))}
        </Row>
      </Screen>
    );
  }

  const label = review ? (i < qs.length - 1 ? 'Next question' : 'Back to results') : i < qs.length - 1 ? 'Next question' : 'Finish';

  return (
    <Screen
      scroll={false}
      padded={false}
      header={
        <Row between style={{ paddingHorizontal: 20, paddingVertical: 10 }}>
          <IconButton icon="x" label="Leave practice" onPress={leave} />
          <View style={{ alignItems: 'center', flex: 1 }}>
            <T variant="h3" numberOfLines={1}>{title ?? q.examSlug.toUpperCase() + ' ' + (year ?? '')}</T>
            <T variant="tiny" muted numberOfLines={1}>{q.subjectSlug.replace(/-/g, ' ')}{q.year ? ` · ${q.year}` : ''}</T>
          </View>
          <IconButton icon="bookmark" label={saved.has(q.id) ? 'Remove from saved' : 'Save this question'} active={saved.has(q.id)} filled onPress={flip} />
        </Row>
      }
      footer={
        <>
          <IconButton icon="chevL" label="Previous question" size={52} onPress={() => setI(Math.max(0, i - 1))} />
          <View style={{ flex: 1 }}><Button label={label} onPress={next} /></View>
        </>
      }
    >
      <ScrollView ref={scroller} contentContainerStyle={{ padding: 20, gap: 16, paddingBottom: 32 }} keyboardShouldPersistTaps="handled">
        <View style={{ gap: 8 }}>
          <Row between><T variant="small" style={{ fontWeight: '700' }}>Question {i + 1} of {qs.length}</T>{q.topic ? <T variant="small" muted>{q.topic}</T> : null}</Row>
          <ProgressBar value={((i + 1) / qs.length) * 100} />
        </View>

        <QuestionView q={q} passage={q.passageId ? session.passages[q.passageId] : null} chosen={answers[q.id]} showAnswer={reveal} onChoose={choose} />

        {reveal ? (
          <>
            <Verdict q={q} chosen={answers[q.id]} />
            <Explanation key={q.id} q={q} lang={lang} />
          </>
        ) : null}

        <Card>
          <Row between style={{ marginBottom: 12 }}><T variant="h3">Answer sheet</T><T variant="small" muted>{answered} of {qs.length} answered</T></Row>
          <AnswerSheet count={qs.length} current={i} stateOf={stateOf} onJump={setI} />
          <Row wrap style={{ gap: 14, marginTop: 12 }}>
            <Legend color={instant || review ? c.primary : c.text} label={instant || review ? 'Right' : 'Answered'} />
            {instant || review ? <Legend color={c.danger} label="Wrong" /> : null}
            <Legend color={c.border} label="Not answered" outline />
          </Row>
        </Card>
        <View style={{ height: insets.bottom }} />
      </ScrollView>
    </Screen>
  );
}

function Legend({ color, label, outline }: { color: string; label: string; outline?: boolean }) {
  return (
    <Row style={{ gap: 6 }}>
      <View style={{ width: 12, height: 12, borderRadius: 6, backgroundColor: outline ? 'transparent' : color, borderWidth: 2, borderColor: color }} />
      <T variant="tiny" muted>{label}</T>
    </Row>
  );
}
