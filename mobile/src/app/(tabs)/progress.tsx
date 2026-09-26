import { router } from 'expo-router';
import { View } from 'react-native';
import { dateTime } from '@/core/format';
import { lastDays, mocks, streak, subjectStats, totals, weakTopics } from '@/db/progress';
import { useFocusLoad } from '@/hooks/useFocusLoad';
import { useData } from '@/state/data';
import { services } from '@/state/services';
import { Button, Card, Empty, ProgressBar, Row, Screen, SubjectTile, T } from '@/ui/components';
import { Icon } from '@/ui/Icon';
import { useTheme } from '@/ui/theme';

export default function Progress() {
  const { c } = useTheme();
  const installed = useData((s) => s.installed);
  const strong = useData((s) => s.catalog?.strong_accuracy ?? 70);

  const { data } = useFocusLoad(async () => {
    const { db } = services();
    const [t, days, week, subjects, topics, recent] = await Promise.all([totals(db), streak(db), lastDays(db, 7), subjectStats(db), weakTopics(db), mocks(db, 5)]);
    return { t, days, week, subjects, topics, recent };
  });

  if (!data) return <Screen scroll={false}><View /></Screen>;

  const { t, days, week, subjects, topics, recent } = data;
  const max = Math.max(1, ...week.map((d) => d.count));
  const thisWeek = week.reduce((n, d) => n + d.count, 0);
  const accuracy = t.answered ? Math.round((t.correct / t.answered) * 100) : 0;
  const best = [...subjects].sort((a, b) => b.accuracy - a.accuracy)[0];
  const worst = subjects[0];

  return (
    <Screen header={<View style={{ paddingHorizontal: 20, paddingTop: 12 }}><T variant="h1">Your progress</T></View>}>
      {t.answered === 0 ? (
        <Empty icon="chart" title="No progress yet" body="Answer some questions in Practice and your scores will show up here."
          action={<Button label="Start practising" full={false} onPress={() => router.push('/practice')} />} />
      ) : (
        <>
          <Row style={{ gap: 8 }}>
            <Card style={{ flex: 1, gap: 4 }}><T variant="tiny" muted>Answered</T><T variant="h2">{t.answered.toLocaleString()}</T></Card>
            <Card style={{ flex: 1, gap: 4 }}><T variant="tiny" muted>Correct</T><T variant="h2">{accuracy}%</T></Card>
            <Card style={{ flex: 1, gap: 4 }}><T variant="tiny" muted>Streak</T><Row style={{ gap: 4 }}><Icon name="flame" size={18} color={c.accent} /><T variant="h2">{days}</T></Row></Card>
          </Row>

          <Card style={{ gap: 14 }}>
            <View><T variant="small" muted>Last 7 days</T><T variant="h1">{thisWeek} <T variant="small" muted>answered</T></T></View>
            <View accessible accessibilityLabel={`Questions answered each day: ${week.map((d) => `${d.label} ${d.count}`).join(', ')}`} style={{ flexDirection: 'row', alignItems: 'flex-end', gap: 10, height: 132 }}>
              {week.map((d) => (
                <View key={d.day} style={{ flex: 1, height: '100%', justifyContent: 'flex-end', alignItems: 'center', gap: 6 }}>
                  <View style={{ width: '100%', height: `${Math.max(3, (d.count / max) * 100)}%`, borderRadius: 8, backgroundColor: d.today ? c.primary : c.primarySoft, maxHeight: 100 }} />
                  <T variant="tiny" muted style={{ fontWeight: '600' }}>{d.label}</T>
                </View>
              ))}
            </View>
          </Card>

          {subjects.length > 1 ? (
            <Row style={{ gap: 12 }}>
              <Card style={{ flex: 1, gap: 4 }}><T variant="tiny" muted>Strongest</T><T variant="h3">{best.subject_name}</T><T variant="h2" color={c.primary}>{best.accuracy}%</T></Card>
              <Card style={{ flex: 1, gap: 4 }}><T variant="tiny" muted>Needs work</T><T variant="h3">{worst.subject_name}</T><T variant="h2" color={c.accentInk}>{worst.accuracy}%</T></Card>
            </Row>
          ) : null}

          <Card style={{ gap: 14 }}>
            <T variant="h2">Score by subject</T>
            {[...subjects].sort((a, b) => b.answered - a.answered).map((s, i) => (
              <Row key={s.subject_id ?? i}>
                <SubjectTile code={(s.subject_name ?? '??').slice(0, 2)} index={i} />
                <View style={{ flex: 1, gap: 6 }}>
                  <Row between><T variant="h3">{s.subject_name}</T><T variant="small" muted>{s.correct} of {s.answered} · {s.accuracy}%</T></Row>
                  <ProgressBar value={s.accuracy} amber={s.accuracy < strong} />
                </View>
              </Row>
            ))}
          </Card>

          {topics.length ? (
            <Card style={{ gap: 4 }}>
              <T variant="h2" style={{ marginBottom: 4 }}>Topics to work on</T>
              {topics.map((tp) => {
                const pack = installed.find((p) => p.subject_slug === tp.subject_slug);
                return (
                  <Row key={`${tp.subject_id}-${tp.topic_id}`} style={{ paddingVertical: 12, borderTopWidth: 1, borderTopColor: c.border }}>
                    <View style={{ flex: 1 }}><T variant="h3">{tp.topic_name}</T><T variant="small" muted>{tp.subject_name}, {tp.correct} of {tp.answered} correct</T></View>
                    {pack ? <Button label="Practise" variant="outline" small full={false}
                      onPress={() => router.push({ pathname: '/practice/session', params: { exam: pack.exam_slug, subject: pack.subject_slug, topic: String(tp.topic_id), mode: 'instant', count: '20' } })} /> : null}
                  </Row>
                );
              })}
            </Card>
          ) : null}

          {recent.length ? (
            <Card style={{ gap: 4 }}>
              <T variant="h2" style={{ marginBottom: 4 }}>Recent mock exams</T>
              {recent.map((m) => (
                <Row key={m.uuid} style={{ paddingVertical: 12, borderTopWidth: 1, borderTopColor: c.border }}>
                  <View style={{ flex: 1 }}><T variant="h3">{m.exam_name} mock</T><T variant="small" muted>{dateTime(m.taken_at)}</T></View>
                  <T variant="h3">{m.score} / {m.total}</T>
                </Row>
              ))}
            </Card>
          ) : null}
        </>
      )}
    </Screen>
  );
}
