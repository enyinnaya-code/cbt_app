import { router } from 'expo-router';
import { useMemo } from 'react';
import { View } from 'react-native';
import { ago, daysUntil, greeting, initials, plural } from '@/core/format';
import { bookmarkedIds, lastPractised, streak, subjectStats } from '@/db/progress';
import { useFocusLoad } from '@/hooks/useFocusLoad';
import { useData } from '@/state/data';
import { services } from '@/state/services';
import { useSession } from '@/state/session';
import { useSettings } from '@/state/settings';
import { Avatar, Badge, Card, Empty, IconButton, ProgressBar, Row, Screen, SubjectTile, T } from '@/ui/components';
import { Icon, type IconName } from '@/ui/Icon';
import { useTheme } from '@/ui/theme';

export default function Home() {
  const { c } = useTheme();
  const user = useSession((s) => s.user);
  const online = useData((s) => s.online);
  const installed = useData((s) => s.installed);
  const catalog = useData((s) => s.catalog);
  const { targetExam, targetExamDate } = useSettings();

  const { data } = useFocusLoad(async () => {
    const { db } = services();
    const [stats, days, last, saved] = await Promise.all([subjectStats(db), streak(db), lastPractised(db), bookmarkedIds(db)]);
    return { stats, days, last, saved: saved.size };
  });

  const left = daysUntil(targetExamDate);
  const examName = useMemo(() => catalog?.exams.find((e) => e.slug === targetExam)?.name ?? targetExam?.toUpperCase() ?? 'Your exam', [catalog, targetExam]);
  const stats = data?.stats ?? [];
  const streakDays = data?.days ?? 0;

  const tiles: { icon: IconName; title: string; note: string; tone: keyof typeof toneOf; to: string }[] = [
    { icon: 'book', title: 'Practice', note: 'By subject and year', tone: 'green', to: '/practice' },
    { icon: 'clock', title: 'Mock exam', note: 'Timed, like CBT', tone: 'amber', to: '/mock' },
    { icon: 'bookmark', title: 'Saved', note: `${data?.saved ?? 0} ${plural(data?.saved ?? 0, 'question')}`, tone: 'blue', to: '/saved' },
    { icon: 'download', title: 'Downloads', note: `${installed.length} ${plural(installed.length, 'subject')}`, tone: 'plum', to: '/downloads' },
  ];
  const toneOf = { green: [c.primarySoft, c.primaryInk], amber: [c.accentSoft, c.accentInk], blue: [c.blueSoft, c.blueInk], plum: [c.plumSoft, c.plumInk] } as const;

  return (
    <Screen>
      <Row between>
        <Row>
          <Avatar text={initials(user?.name ?? '')} />
          <View>
            <T variant="small" muted>{greeting()}</T>
            <T variant="h3">{user?.name.split(' ')[0]}</T>
          </View>
        </Row>
        <Badge label={online ? 'Online' : 'Offline'} kind={online ? 'neutral' : 'green'} icon={online ? 'wifi' : 'wifioff'} />
      </Row>

      <Card kind="primary">
        {left !== null ? (
          <>
            <T variant="small" color={c.onPrimary} style={{ opacity: 0.85 }}>{examName}</T>
            <Row between style={{ alignItems: 'flex-end', marginTop: 4 }}>
              <T variant="display" color={c.onPrimary}>{left} <T variant="h2" color={c.onPrimary}>{plural(left, 'day')} left</T></T>
              <Row style={{ gap: 4 }}><Icon name="flame" size={15} color={c.onPrimary} /><T variant="small" color={c.onPrimary} style={{ fontWeight: '700' }}>{streakDays}-day streak</T></Row>
            </Row>
          </>
        ) : (
          <Row between style={{ alignItems: 'flex-end' }}>
            <View>
              <T variant="small" color={c.onPrimary} style={{ opacity: 0.85 }}>Study streak</T>
              <T variant="display" color={c.onPrimary}>{streakDays} <T variant="h2" color={c.onPrimary}>{plural(streakDays, 'day')}</T></T>
            </View>
            <T variant="small" color={c.onPrimary} style={{ fontWeight: '700' }} onPress={() => router.push('/profile')} accessibilityRole="link">Set your exam date</T>
          </Row>
        )}
      </Card>

      <Card>
        {data?.last ? (
          <View style={{ gap: 12 }}>
            <Row between><T variant="h3">Pick up where you stopped</T>{data.last.year ? <Badge label={`${data.last.exam_slug.toUpperCase()} ${data.last.year}`} /> : null}</Row>
            <Row>
              <SubjectTile code={data.last.subject_name.slice(0, 2)} index={2} />
              <View style={{ flex: 1 }}><T variant="h3">{data.last.subject_name}</T><T variant="small" muted>Last practised {ago(data.last.answered_at)}</T></View>
              <IconButton icon="play" label={`Continue ${data.last.subject_name}`} bg={c.primary} color={c.onPrimary} size={44}
                onPress={() => router.push({ pathname: '/practice', params: { exam: data.last!.exam_slug, subject: data.last!.subject_slug } })} />
            </Row>
          </View>
        ) : (
          <View style={{ gap: 8 }}>
            <T variant="h3">Start your first practice</T>
            <T variant="small" muted>Choose a subject, answer a few questions, and your progress will show here.</T>
          </View>
        )}
      </Card>

      <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 12 }}>
        {tiles.map((t) => (
          <Card key={t.title} onPress={() => router.push(t.to as never)} accessibilityLabel={`${t.title}. ${t.note}`} style={{ width: '47.6%', gap: 12 }}>
            <View style={{ width: 40, height: 40, borderRadius: 12, backgroundColor: toneOf[t.tone][0], alignItems: 'center', justifyContent: 'center' }}>
              <Icon name={t.icon} size={20} color={toneOf[t.tone][1]} />
            </View>
            <View><T variant="h3">{t.title}</T><T variant="small" muted>{t.note}</T></View>
          </Card>
        ))}
      </View>

      <View style={{ gap: 4 }}>
        <T variant="h2">Your subjects</T>
        {stats.length === 0 ? (
          <Empty icon="chart" title="Nothing here yet" body="Your score in each subject will appear once you have answered some questions." />
        ) : (
          stats.slice(0, 4).map((s, i) => (
            <Row key={s.subject_id ?? i} style={{ paddingVertical: 12, borderBottomWidth: i < Math.min(stats.length, 4) - 1 ? 1 : 0, borderBottomColor: c.border }}>
              <SubjectTile code={(s.subject_name ?? '??').slice(0, 2)} index={i} />
              <View style={{ flex: 1, gap: 6 }}>
                <Row between><T variant="h3">{s.subject_name}</T><T variant="small" muted>{s.accuracy}% of {s.answered}</T></Row>
                <ProgressBar value={s.accuracy} amber={s.accuracy < (catalog?.strong_accuracy ?? 70)} />
              </View>
            </Row>
          ))
        )}
      </View>
    </Screen>
  );
}
