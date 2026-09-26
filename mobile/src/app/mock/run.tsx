import { router, useLocalSearchParams } from 'expo-router';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Alert, AppState, ScrollView, View } from 'react-native';
import { clock } from '@/core/format';
import { activeMock, finishMock, saveProgress, type MockRun } from '@/services/mockRuns';
import { useData } from '@/state/data';
import { services } from '@/state/services';
import { Button, Card, Chip, Empty, IconButton, Row, Screen, T } from '@/ui/components';
import { Icon } from '@/ui/Icon';
import { AnswerSheet, QuestionView, type SheetState } from '@/ui/QuestionView';
import { useTheme } from '@/ui/theme';

export default function MockRunScreen() {
  const { uuid } = useLocalSearchParams<{ uuid?: string }>();
  const [state, setState] = useState<{ run: MockRun; answers: Record<number, string>; flagged: number[]; position: number } | null | undefined>(undefined);

  useEffect(() => {
    activeMock(services().db).then((a) => {
      if (!a || (uuid && a.run.uuid !== uuid)) { setState(null); return; }
      setState({ run: a.run, answers: a.progress.answers, flagged: a.progress.flagged, position: a.progress.position });
    });
  }, [uuid]);

  if (state === undefined) return <Screen scroll={false}><View /></Screen>;
  if (state === null) {
    return (
      <Screen>
        <Empty icon="clock" title="This exam is not open" body="It may already have been submitted." action={<Button label="Back to Mock" full={false} onPress={() => router.replace('/mock')} />} />
      </Screen>
    );
  }
  return <Exam run={state.run} initial={state} />;
}

function Exam({ run, initial }: { run: MockRun; initial: { answers: Record<number, string>; flagged: number[]; position: number } }) {
  const { c } = useTheme();
  const qs = run.questions;
  const deadline = new Date(run.deadlineAt).getTime();

  const [i, setI] = useState(Math.min(initial.position, qs.length - 1));
  const [answers, setAnswers] = useState<Record<number, string>>(initial.answers);
  const [flagged, setFlagged] = useState<number[]>(initial.flagged);
  const [left, setLeft] = useState(() => Math.max(0, Math.round((deadline - Date.now()) / 1000)));
  const [submitting, setSubmitting] = useState(false);
  const submittingRef = useRef(false);
  const latest = useRef({ answers, flagged, i });
  latest.current = { answers, flagged, i };
  const scroller = useRef<ScrollView>(null);

  // Where each subject starts and ends.
  const ranges = run.groups.reduce<{ start: number; end: number; name: string; count: number }[]>((acc, g) => {
    const start = acc.length ? acc[acc.length - 1].end + 1 : 0;
    acc.push({ start, end: start + g.count - 1, name: g.name, count: g.count });
    return acc;
  }, []);
  const groupIndex = ranges.findIndex((r) => i >= r.start && i <= r.end);
  const group = ranges[groupIndex];
  const q = qs[i];

  const answeredIn = (r: { start: number; end: number }) => qs.slice(r.start, r.end + 1).filter((x) => answers[x.id] !== undefined).length;

  // Save quietly a moment after each change, and again the instant the app goes to the background.
  const save = useCallback(async () => {
    const l = latest.current;
    await saveProgress(services().db, run.uuid, { answers: l.answers, flagged: l.flagged, position: l.i });
  }, [run.uuid]);

  useEffect(() => {
    const t = setTimeout(() => void save(), 400);
    return () => clearTimeout(t);
  }, [answers, flagged, i, save]);

  useEffect(() => {
    const sub = AppState.addEventListener('change', (s) => { if (s !== 'active') void save(); });
    return () => { sub.remove(); void save(); };
  }, [save]);

  const submit = useCallback(async (auto: boolean) => {
    if (submittingRef.current) return;
    submittingRef.current = true;
    setSubmitting(true);
    try {
      const { db, newUuid } = services();
      await finishMock(db, useData.getState().index, run, latest.current.answers, newUuid);
      void useData.getState().refreshPending();
      void useData.getState().syncNow();
      router.replace({ pathname: '/mock/result', params: { uuid: run.uuid, ...(auto ? { auto: '1' } : {}) } });
    } catch {
      submittingRef.current = false;
      setSubmitting(false);
      Alert.alert('Could not submit', 'Something went wrong on your phone. Please try again.');
    }
  }, [run]);

  // The clock comes from a fixed moment, so it is right after a restart and cannot be paused by closing the app.
  useEffect(() => {
    const tick = () => {
      const s = Math.max(0, Math.round((deadline - Date.now()) / 1000));
      setLeft(s);
      if (s <= 0) void submit(true);
    };
    tick();
    const id = setInterval(tick, 1000);
    return () => clearInterval(id);
  }, [deadline, submit]);

  useEffect(() => { scroller.current?.scrollTo({ y: 0, animated: false }); }, [i]);

  const choose = (letter: string) => {
    setAnswers((a) => { const n = { ...a }; if (n[q.id] === letter) delete n[q.id]; else n[q.id] = letter; return n; });
  };

  const confirmSubmit = () => {
    const answered = Object.keys(answers).length;
    const unanswered = qs.length - answered;
    const flags = flagged.length;
    Alert.alert(
      'Submit your exam?',
      `${answered} of ${qs.length} answered${unanswered ? `\n${unanswered} not answered` : ''}${flags ? `\n${flags} flagged` : ''}\n\nYou cannot change your answers after you submit.`,
      [{ text: 'Keep working', style: 'cancel' }, { text: 'Submit', style: 'destructive', onPress: () => void submit(false) }],
    );
  };

  const leave = () => {
    Alert.alert('Leave for now?', 'Your exam stays open and the clock keeps running. You can resume it from the Mock tab.', [{ text: 'Stay', style: 'cancel' }, { text: 'Leave', onPress: () => router.replace('/mock') }]);
  };

  const stateOf = (index: number): SheetState => {
    const id = qs[index].id;
    if (flagged.includes(id)) return 'flagged';
    return answers[id] !== undefined ? 'answered' : 'none';
  };

  const isFlagged = flagged.includes(q.id);
  const low = left <= 300;

  return (
    <Screen
      scroll={false}
      padded={false}
      header={
        <View style={{ paddingHorizontal: 20, paddingTop: 10, gap: 10 }}>
          <Row between>
            <IconButton icon="chevL" label="Leave the exam for now" onPress={leave} />
            <View style={{ flex: 1 }}><T variant="h3" numberOfLines={1}>{run.label}</T><T variant="tiny" muted>{ranges.length} {ranges.length === 1 ? 'subject' : 'subjects'}</T></View>
            <View accessibilityRole="timer" style={{ flexDirection: 'row', alignItems: 'center', gap: 6, paddingHorizontal: 12, paddingVertical: 8, borderRadius: 12, backgroundColor: low ? c.dangerSoft : c.accentSoft }}>
              <Icon name="clock" size={15} color={low ? c.danger : c.accentInk} />
              <T variant="h3" color={low ? c.danger : c.accentInk} style={{ fontVariant: ['tabular-nums'] }}>{clock(left)}</T>
            </View>
          </Row>
          {ranges.length > 1 ? (
            <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 8 }}>
              {ranges.map((r, idx) => <Chip key={r.name} label={`${r.name} ${answeredIn(r)}/${r.count}`} selected={idx === groupIndex} onPress={() => setI(r.start)} />)}
            </ScrollView>
          ) : null}
        </View>
      }
      footer={
        <>
          <View style={{ flex: 1 }}><Button label="Previous" variant="outline" disabled={i === 0 || submitting} onPress={() => setI(i - 1)} /></View>
          <View style={{ flex: 1 }}><Button label={i < qs.length - 1 ? 'Next' : 'Review and submit'} disabled={submitting} onPress={() => (i < qs.length - 1 ? setI(i + 1) : confirmSubmit())} /></View>
        </>
      }
    >
      <ScrollView ref={scroller} contentContainerStyle={{ padding: 20, gap: 16, paddingBottom: 32 }} keyboardShouldPersistTaps="handled">
        <Row between>
          <T variant="h3">Question {i - group.start + 1} <T variant="small" muted>of {group.count}</T></T>
          <Chip label="Flag" icon="flag" selected={isFlagged} onPress={() => setFlagged((f) => (f.includes(q.id) ? f.filter((x) => x !== q.id) : [...f, q.id]))} />
        </Row>

        <QuestionView q={q} passage={q.passageId ? run.passages[q.passageId] : null} chosen={answers[q.id]} showAnswer={false} onChoose={choose} />

        <Card>
          <Row between style={{ marginBottom: 12 }}><T variant="h3">Answer sheet</T><T variant="small" muted>{group.name}: {answeredIn(group)} of {group.count} answered</T></Row>
          <AnswerSheet count={group.count} offset={group.start} current={i} stateOf={stateOf} onJump={setI} />
          <Row wrap style={{ gap: 14, marginTop: 12 }}>
            <Legend color={c.text} label="Answered" />
            <Legend color={c.accent} label="Flagged" />
            <Legend color={c.border} label="Not answered" outline />
          </Row>
          <View style={{ marginTop: 14 }}><Button label="Submit exam" variant="outline" disabled={submitting} onPress={confirmSubmit} /></View>
        </Card>
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
