import { router, useLocalSearchParams } from 'expo-router';
import { useEffect, useState } from 'react';
import { ScrollView, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { plural } from '@/core/format';
import { finishedMock } from '@/services/mockRuns';
import { useData } from '@/state/data';
import { services } from '@/state/services';
import { useSettings } from '@/state/settings';
import { Badge, Button, Card, Chip, Empty, IconButton, ProgressBar, Row, Screen, T } from '@/ui/components';
import { Explanation, QuestionView, Verdict } from '@/ui/QuestionView';
import { useTheme } from '@/ui/theme';

type Loaded = NonNullable<Awaited<ReturnType<typeof finishedMock>>>;

export default function MockResult() {
  const { c } = useTheme();
  const { uuid, auto } = useLocalSearchParams<{ uuid?: string; auto?: string }>();
  const strong = useData((s) => s.catalog?.strong_accuracy ?? 70);
  const [data, setData] = useState<Loaded | null | undefined>(undefined);
  const [reviewing, setReviewing] = useState(false);

  useEffect(() => { finishedMock(services().db, uuid ?? '').then(setData); }, [uuid]);

  if (data === undefined) return <Screen scroll={false}><View /></Screen>;
  if (data === null) {
    return <Screen><Empty icon="clock" title="Result not found" action={<Button label="Back to Mock" full={false} onPress={() => router.replace('/mock')} />} /></Screen>;
  }

  if (reviewing) return <Review data={data} onClose={() => setReviewing(false)} />;

  const { result: r, run } = data;
  const secs = r.durationSeconds;
  const used = secs < 60 ? `${secs}s` : `${secs >= 3600 ? `${Math.floor(secs / 3600)}h ` : ''}${Math.floor((secs % 3600) / 60)}m`;
  const correctPct = r.questions ? Math.round((r.correct / r.questions) * 100) : 0;
  const weak = r.topics.filter((t) => t.total >= 2 && (t.correct / t.total) * 100 < strong).sort((a, b) => a.correct / a.total - b.correct / b.total).slice(0, 6);
  const pct = r.total ? r.score / r.total : 0;

  return (
    <Screen footer={<><Button label="Review answers" variant="outline" full={false} style={{ flex: 1 }} onPress={() => setReviewing(true)} /><Button label="Done" full={false} style={{ flex: 1 }} onPress={() => router.replace('/mock')} /></>}>
      <Row between><View><T variant="small" muted>{run.label}</T><T variant="h2">Your result</T></View><IconButton icon="x" label="Close" onPress={() => router.replace('/mock')} /></Row>
      {auto === '1' ? <Badge label="Time was up, so your answers were marked" kind="amber" icon="clock" /> : null}

      <View style={{ alignItems: 'center', gap: 10, paddingVertical: 8 }}>
        <View accessible accessibilityLabel={`Score ${r.score} out of ${r.total}`} style={{ width: 180, height: 180, borderRadius: 90, borderWidth: 12, borderColor: pct >= 0.5 ? c.primary : c.accent, alignItems: 'center', justifyContent: 'center' }}>
          <T variant="display" style={{ fontSize: 48 }}>{r.score}</T>
          <T variant="small" muted>out of {r.total}</T>
        </View>
        {r.change !== null ? <Badge kind={r.change >= 0 ? 'green' : 'red'} icon="trophy" label={`${Math.abs(r.change)} ${plural(Math.abs(r.change), 'point')} ${r.change >= 0 ? 'higher' : 'lower'} than your last mock`} /> : null}
      </View>

      <Row style={{ gap: 8 }}>
        {[['Time used', used], ['Answered', `${r.answered}/${r.questions}`], ['Correct', `${correctPct}%`]].map(([label, v]) => (
          <Card key={label} style={{ flex: 1, gap: 4 }}><T variant="tiny" muted>{label}</T><T variant="h3">{v}</T></Card>
        ))}
      </Row>

      <Card style={{ gap: 14 }}>
        <T variant="h3">Score by subject</T>
        {r.groups.map((g) => (
          <View key={g.subjectId} style={{ gap: 6 }}>
            <Row between><T variant="small">{g.name}</T><T variant="small" style={{ fontWeight: '700' }}>{g.correct} of {g.total} · {g.percent}</T></Row>
            <ProgressBar value={g.percent} amber={g.percent < strong} />
          </View>
        ))}
        {r.groups.length > 1 ? <T variant="tiny" muted>Each subject is marked out of 100, so the total is out of {r.total}.</T> : null}
      </Card>

      {weak.length ? (
        <View style={{ gap: 10 }}>
          <T variant="h3">Topics to work on</T>
          <Row wrap style={{ gap: 8 }}>{weak.map((t) => <Chip key={t.topicId} label={`${t.name} ${t.correct}/${t.total}`} />)}</Row>
        </View>
      ) : null}
    </Screen>
  );
}

function Review({ data, onClose }: { data: Loaded; onClose: () => void }) {
  const insets = useSafeAreaInsets();
  const lang = useSettings((s) => s.lang);
  const { run, answers } = data;

  return (
    <Screen scroll={false} padded={false} header={<Row between style={{ paddingHorizontal: 20, paddingVertical: 10 }}><T variant="h2">Review answers</T><IconButton icon="x" label="Close review" onPress={onClose} /></Row>}>
      <ScrollView contentContainerStyle={{ padding: 20, gap: 28, paddingBottom: insets.bottom + 24 }}>
        {run.questions.map((q, n) => (
          <View key={q.id} style={{ gap: 14 }}>
            <Row between><T variant="h3">Question {n + 1}</T><Badge kind={answers[q.id] === q.answer ? 'green' : answers[q.id] ? 'red' : 'neutral'} label={answers[q.id] === q.answer ? 'Correct' : answers[q.id] ? 'Wrong' : 'Not answered'} /></Row>
            <QuestionView q={q} passage={q.passageId ? run.passages[q.passageId] : null} chosen={answers[q.id]} showAnswer onChoose={() => {}} />
            <Verdict q={q} chosen={answers[q.id]} />
            <Explanation q={q} lang={lang} />
          </View>
        ))}
      </ScrollView>
    </Screen>
  );
}
