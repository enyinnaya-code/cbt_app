import { router } from 'expo-router';
import { useMemo } from 'react';
import { Alert, Linking, View } from 'react-native';
import { CHALLENGE } from '@/core/challenge';
import { ago, daysUntil, greeting, initials, plural } from '@/core/format';
import { endingSoon, pendingUpgrades } from '@/core/purchases';
import { INSTALLED_VERSION } from '@/core/version';
import { buildViews } from '@/core/views';
import { startDownload } from '@/ui/downloads';
import { unlockOnWebsite } from '@/ui/unlock';
import { bookmarkedIds, lastPractised, streak, subjectStats } from '@/db/progress';
import { useFocusLoad } from '@/hooks/useFocusLoad';
import { checkForUpdate, dismissUpdate } from '@/services/appUpdate';
import { closeNotice, currentNotices, safeLink } from '@/services/notices';
import { todaysChallenge } from '@/services/challenge';
import { isJoined } from '@/services/rankings';
import { engagement } from '@/services/engagement';
import { useData } from '@/state/data';
import { services } from '@/state/services';
import { useSession } from '@/state/session';
import { useSettings } from '@/state/settings';
import { Avatar, Badge, Button, Card, Empty, IconButton, ProgressBar, Row, Screen, SubjectTile, T } from '@/ui/components';
import { Icon, type IconName } from '@/ui/Icon';
import { useTheme } from '@/ui/theme';

export default function Home() {
  const { c } = useTheme();
  const user = useSession((s) => s.user);
  const online = useData((s) => s.online);
  const installed = useData((s) => s.installed);
  const catalog = useData((s) => s.catalog);
  const { targetExam, targetExamDate } = useSettings();

  const { data, reload } = useFocusLoad(async () => {
    const { db } = services();
    // The daily challenge feeds the rankings, so it is opt-in: only students who joined get one.
    const joined = await isJoined(db);
    const [stats, days, last, saved, level, challenge, update, notices] = await Promise.all([
      subjectStats(db), streak(db), lastPractised(db), bookmarkedIds(db), engagement(db),
      joined ? todaysChallenge(db, services().packs, String(user?.id ?? ''), targetExam) : Promise.resolve(null),
      checkForUpdate(db, services().api, INSTALLED_VERSION),
      currentNotices(db, services().api),
    ]);
    return { stats, days, last, saved: saved.size, level, challenge, joined, update, notices };
  });

  const openUpdate = async (url: string) => {
    try {
      await Linking.openURL(url);
    } catch {
      Alert.alert('Could not open the page', 'Please try again in a moment, or open the TestaCBT website to download the new version.');
    }
  };

  // What the student bought but the phone does not have yet, and what is about to run out.
  const upgrades = useMemo(() => pendingUpgrades(buildViews(catalog, installed)), [catalog, installed]);
  const ending = useMemo(() => endingSoon(catalog), [catalog]);

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

      {data?.notices.map((n) => {
        const link = safeLink(n.link_url);
        const bg = n.tone === 'warning' ? c.accentSoft : n.tone === 'success' ? c.primarySoft : c.blueSoft;
        return (
          <Card key={n.id} style={{ gap: 8, backgroundColor: bg, borderColor: bg }}>
            <Row between style={{ alignItems: 'flex-start' }}>
              <T variant="h3" style={{ flex: 1 }}>{n.title}</T>
              <IconButton icon="x" label="Close this announcement" size={34} bg="transparent" onPress={() => { void closeNotice(services().db, n.id).then(reload); }} />
            </Row>
            {n.body ? <T variant="small">{n.body}</T> : null}
            {link ? <Button full={false} small label={n.link_label || 'Read more'} onPress={() => void openUpdate(link)} /> : null}
          </Card>
        );
      })}

      {data?.update ? (
        <Card style={{ gap: 10, backgroundColor: c.accentSoft, borderColor: c.accentSoft }}>
          <Row>
            <Icon name="download" size={18} color={c.accentInk} />
            <T variant="h3" style={{ flex: 1 }}>A new version is ready</T>
          </Row>
          <T variant="small">TestaCBT {data.update.latest} has new features. You have {INSTALLED_VERSION}. Your progress and downloads stay safe when you update.</T>
          <Row wrap style={{ gap: 8 }}>
            <Button full={false} label="Update now" icon="download" onPress={() => void openUpdate(data.update!.url)} />
            <Button full={false} variant="outline" label="Later" onPress={() => { void dismissUpdate(services().db, data.update!.latest).then(reload); }} />
          </Row>
        </Card>
      ) : null}

      {upgrades.map((s) => (
        <Card key={`${s.examSlug}/${s.slug}`} style={{ gap: 8, backgroundColor: c.accentSoft, borderColor: c.accentSoft }}>
          <T variant="h3">{s.name}: your full questions are ready</T>
          <T variant="small">You unlocked it, but this phone still has the free sample. Download the full set to use every question.</T>
          <Button full={false} icon="download" label="Download the full set" onPress={() => startDownload(s)} />
        </Card>
      ))}

      {ending.map((e) => (
        <Card key={`${e.examSlug}/${e.subjectSlug}`} style={{ gap: 8, backgroundColor: c.accentSoft, borderColor: c.accentSoft }}>
          <T variant="h3">{e.subject} ends {e.days <= 1 ? 'tomorrow' : `in ${e.days} days`}</T>
          <T variant="small">Renew to keep every question. Your progress stays either way.</T>
          <Button full={false} small label="Renew" icon="lock" onPress={() => void unlockOnWebsite({ exam: e.examSlug, subject: e.subjectSlug })} />
        </Card>
      ))}

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

      {data?.level ? (
        <>
          <T variant="small" muted>{data.level.message}</T>
          <Card style={{ gap: 12 }}>
            <Row>
              <View style={{ width: 42, height: 42, borderRadius: 12, backgroundColor: c.accentSoft, alignItems: 'center', justifyContent: 'center' }}>
                <Icon name="trophy" size={20} color={c.accentInk} />
              </View>
              <View style={{ flex: 1 }}>
                <T variant="h3">Level {data.level.level.level}: {data.level.level.title}</T>
                <T variant="small" muted>{data.level.level.xp.toLocaleString()} XP{data.level.xpToday ? ` · +${data.level.xpToday} today` : ''}</T>
              </View>
            </Row>
            <ProgressBar value={data.level.level.pct} />
            <T variant="small" muted>
              {data.level.level.toNext !== null ? `${data.level.level.toNext.toLocaleString()} XP to ${data.level.level.nextTitle}` : 'You have reached the top level.'}
            </T>
          </Card>
        </>
      ) : null}

      {data && !data.joined ? (
        <Card style={{ gap: 10 }}>
          <T variant="h3">Daily challenge</T>
          <T variant="small" muted>10 questions a day, ranked against other students.</T>
          <Button full={false} label="Join the daily challenge" onPress={() => router.push('/rankings/join' as never)} />
        </Card>
      ) : null}

      {data?.challenge ? (
        <Card style={{ gap: 12 }}>
          <Row between>
            <T variant="h3">{"Today's challenge"}</T>
            <Badge label={data.challenge.done ? 'Done' : `+${CHALLENGE.bonus} XP`} kind={data.challenge.done ? 'green' : 'neutral'} />
          </Row>
          <T variant="small" muted>{data.challenge.subject ? `${data.challenge.subject} · ` : ''}{data.challenge.total} questions</T>
          {data.challenge.done ? (
            <>
              <T variant="small">You scored {data.challenge.correct} of {data.challenge.total}. A new challenge is ready tomorrow.</T>
              <Button full={false} variant="outline" label="See rankings" onPress={() => router.push('/rankings' as never)} />
            </>
          ) : (
            <>
              <ProgressBar value={data.challenge.total ? (data.challenge.answered / data.challenge.total) * 100 : 0} />
              <Row wrap style={{ gap: 8 }}>
                <Button full={false} label={data.challenge.answered ? `Continue (${data.challenge.answered}/${data.challenge.total})` : 'Start challenge'}
                  onPress={() => router.push({ pathname: '/practice/session', params: { challenge: '1' } })} />
                <Button full={false} variant="outline" label="Rankings" onPress={() => router.push('/rankings' as never)} />
              </Row>
            </>
          )}
        </Card>
      ) : null}

      <Card>
        {data?.last ? (
          <View style={{ gap: 12 }}>
            <T variant="h3">Pick up where you stopped</T>
            <Row style={{ alignItems: 'flex-start' }}>
              <SubjectTile code={data.last.subject_name.slice(0, 2)} index={2} />
              <View style={{ flex: 1, minWidth: 0, gap: 2 }}>
                <T variant="h3">{data.last.subject_name}</T>
                {data.last.year ? <T variant="small" style={{ fontWeight: '700' }}>{data.last.exam_slug.toUpperCase()} {data.last.year}</T> : null}
                <T variant="small" muted>Last practised {ago(data.last.answered_at)}</T>
              </View>
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
            <Row key={s.subject_id ?? i} style={{ alignItems: 'flex-start', paddingVertical: 12, borderBottomWidth: i < Math.min(stats.length, 4) - 1 ? 1 : 0, borderBottomColor: c.border }}>
              <SubjectTile code={(s.subject_name ?? '??').slice(0, 2)} index={i} />
              <View style={{ flex: 1, minWidth: 0, gap: 6 }}>
                <View style={{ gap: 2 }}><T variant="h3">{s.subject_name}</T><T variant="small" muted>{s.accuracy}% of {s.answered}</T></View>
                <ProgressBar value={s.accuracy} amber={s.accuracy < (catalog?.strong_accuracy ?? 70)} />
              </View>
            </Row>
          ))
        )}
      </View>
    </Screen>
  );
}
