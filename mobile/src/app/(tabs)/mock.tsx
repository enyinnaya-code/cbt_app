import { router } from 'expo-router';
import { useMemo, useState } from 'react';
import { Alert, Pressable, View } from 'react-native';
import { clock, dateTime, plural } from '@/core/format';
import { MIN_QUESTIONS, plannedQuestions, validateChoice, type SubjectPack } from '@/core/mock';
import { buildViews, formatFor } from '@/core/views';
import { mocks } from '@/db/progress';
import { useFocusLoad } from '@/hooks/useFocusLoad';
import { activeMock, finishMock, isExpired, startMock } from '@/services/mockRuns';
import { useData } from '@/state/data';
import { services } from '@/state/services';
import { useSettings } from '@/state/settings';
import { Badge, Banner, Button, Card, Empty, Row, Screen, Segmented, SubjectTile, T } from '@/ui/components';
import { Icon } from '@/ui/Icon';
import { useTheme } from '@/ui/theme';

export default function Mock() {
  const { c } = useTheme();
  const catalog = useData((s) => s.catalog);
  const installed = useData((s) => s.installed);
  const targetExam = useSettings((s) => s.targetExam);
  const views = useMemo(() => buildViews(catalog, installed), [catalog, installed]);

  const [examSlug, setExamSlug] = useState<string | null>(null);
  const [picked, setPicked] = useState<string[]>([]);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const exam = views.find((v) => v.slug === examSlug) ?? views.find((v) => v.slug === targetExam) ?? views.find((v) => v.subjects.filter((s) => s.installed).length >= 1) ?? views[0];
  const format = exam ? formatFor(catalog, exam.slug, exam.name) : null;

  const { data } = useFocusLoad(async () => {
    const { db, newUuid } = services();
    let active = await activeMock(db);

    // Time ran out while the student was away: mark what they had, like the real exam would.
    if (active && isExpired(active.run)) {
      await finishMock(db, useData.getState().index, active.run, active.progress.answers, newUuid);
      void useData.getState().syncNow();
      const uuid = active.run.uuid;
      active = null;
      router.push({ pathname: '/mock/result', params: { uuid } });
    }
    return { active, recent: await mocks(db, 3) };
  });

  const available = exam?.subjects.filter((s) => s.installed && s.installed.question_count >= MIN_QUESTIONS) ?? [];
  const compulsory = format?.compulsory ?? null;
  const needMore = format ? format.subject_count - (compulsory ? 1 : 0) : 0;

  const chosen = useMemo(() => {
    const set = new Set(picked.filter((p) => available.some((a) => a.slug === p)));
    if (compulsory && available.some((a) => a.slug === compulsory)) set.add(compulsory);
    return [...set];
  }, [picked, available, compulsory]);

  const canStart = !!format && chosen.length === format.subject_count && !busy;
  const planned = format ? chosen.reduce((n, slug) => n + Math.min(plannedQuestions(format, slug), available.find((a) => a.slug === slug)?.installed?.question_count ?? 0), 0) : 0;
  const full = format ? chosen.reduce((n, slug) => n + plannedQuestions(format, slug), 0) : 0;
  const minutes = format ? (planned < full ? Math.max(5, Math.ceil((format.minutes * planned) / full)) : format.minutes) : 0;

  const toggle = (slug: string) => {
    if (!format || slug === compulsory) return;
    setPicked((p) => (p.includes(slug) ? p.filter((x) => x !== slug) : p.length < needMore ? [...p, slug] : p));
  };

  const begin = async () => {
    if (!exam || !format) return;
    const problem = validateChoice(format, chosen);
    if (problem) { setError(problem); return; }

    setBusy(true);
    setError(null);
    try {
      const { packs, db, newUuid } = services();
      const chosenPacks: SubjectPack[] = [];
      for (const slug of chosen) {
        const view = exam.subjects.find((s) => s.slug === slug);
        const pack = await packs.load(exam.slug, slug);
        if (!pack) throw new Error(`${view?.name ?? slug} is not on your phone. Download it first.`);
        chosenPacks.push({ subjectId: view?.subjectId ?? 0, pack });
      }

      const run = await startMock(db, { uuid: newUuid(), examSlug: exam.slug, examId: exam.examId ?? 0, examName: exam.name, format, chosen: chosenPacks });
      router.push({ pathname: '/mock/run', params: { uuid: run.uuid } });
      setPicked([]);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Could not start the mock exam.');
    } finally {
      setBusy(false);
    }
  };

  const confirmStart = () => {
    if (data?.active) {
      Alert.alert('You already have a mock in progress', 'Finish it first, or resume it.', [{ text: 'OK' }]);
      return;
    }
    void begin();
  };

  const left = data?.active ? Math.max(0, Math.round((new Date(data.active.run.deadlineAt).getTime() - Date.now()) / 1000)) : 0;

  return (
    <Screen
      header={<View style={{ paddingHorizontal: 20, paddingTop: 12 }}><T variant="h1">Mock exam</T><T muted>Timed like the real thing. Nothing is marked until you submit.</T></View>}
      footer={format && available.length >= format.subject_count ? <Button label="Start mock exam" disabled={!canStart} loading={busy} onPress={confirmStart} /> : undefined}
    >
      {data?.active ? (
        <Card kind="primary" style={{ gap: 10 }}>
          <T variant="h3" color={c.onPrimary}>You have a mock exam in progress</T>
          <T variant="small" color={c.onPrimary} style={{ opacity: 0.9 }}>{data.active.run.label} · {clock(left)} left. The clock has kept running.</T>
          <Button label="Resume exam" variant="outline" onPress={() => router.push({ pathname: '/mock/run', params: { uuid: data.active!.run.uuid } })} />
        </Card>
      ) : null}

      {views.length === 0 ? (
        <Empty icon="clock" title="No questions on this phone yet" body="Connect to the internet once to download your subjects." />
      ) : (
        <>
          <Segmented value={exam?.slug ?? ''} onChange={(v) => { setExamSlug(v); setPicked([]); setError(null); }} options={views.map((v) => ({ value: v.slug, label: v.name }))} />

          {format ? (
            <Card kind="flat">
              <Row wrap style={{ gap: 24 }}>
                <View><T variant="tiny" muted>Format</T><T variant="h3">{format.label}</T></View>
                <View><T variant="tiny" muted>Questions</T><T variant="h3">{planned || full}</T></View>
                <View><T variant="tiny" muted>Time</T><T variant="h3">{minutes >= 60 ? `${Math.floor(minutes / 60)} h ${minutes % 60 ? `${minutes % 60} min` : ''}` : `${minutes} min`}</T></View>
                <View><T variant="tiny" muted>Scored out of</T><T variant="h3">{100 * format.subject_count}</T></View>
              </Row>
            </Card>
          ) : null}

          {format && available.length < format.subject_count ? (
            <Empty icon="clock" title={`The ${exam.name} mock needs ${format.subject_count} ${plural(format.subject_count, 'subject')}`} body={`Only ${available.length} on your phone ${available.length === 1 ? 'has' : 'have'} enough questions. Download more subjects to unlock it.`}
              action={<Button label="Manage downloads" full={false} variant="outline" onPress={() => router.push('/downloads')} />} />
          ) : (
            <View style={{ gap: 10 }}>
              <T variant="h3">{compulsory ? `Choose ${needMore} more ${plural(needMore, 'subject')}` : format?.subject_count === 1 ? 'Choose a subject' : `Choose ${format?.subject_count} subjects`}</T>
              <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 12 }}>
                {[...available].sort((a, b) => Number(b.slug === compulsory) - Number(a.slug === compulsory)).map((s) => {
                  const on = chosen.includes(s.slug);
                  const locked = s.slug === compulsory;
                  const have = s.installed?.question_count ?? 0;
                  const want = format ? plannedQuestions(format, s.slug) : 0;
                  return (
                    <Pressable
                      key={s.slug}
                      accessibilityRole="checkbox"
                      accessibilityState={{ checked: on, disabled: locked }}
                      accessibilityLabel={`${s.name}${locked ? ', compulsory' : ''}. ${Math.min(have, want)} questions`}
                      onPress={() => toggle(s.slug)}
                      style={{ width: '47.6%', padding: 14, gap: 12, borderRadius: 18, backgroundColor: c.surface, borderWidth: on ? 2 : 1, borderColor: on ? c.primary : c.border }}
                    >
                      <Row between>
                        <SubjectTile code={s.code} index={s.index} size={40} />
                        {locked ? <Badge label="Compulsory" kind="green" /> : on ? <Icon name="check" size={18} color={c.primary} /> : null}
                      </Row>
                      <View><T variant="h3">{s.name}</T><T variant="small" muted>{Math.min(have, want)} questions{have < want ? ', all we have' : ''}</T></View>
                    </Pressable>
                  );
                })}
              </View>
              <T variant="tiny" muted>If a subject has fewer questions than the real exam, the mock is shorter and the clock is shortened to match.</T>
            </View>
          )}

          {error ? <Banner kind="error" text={error} /> : null}

          {data && data.recent.length > 0 ? (
            <View style={{ gap: 4 }}>
              <T variant="h2">Your recent mocks</T>
              {data.recent.map((m) => (
                <Pressable key={m.uuid} accessibilityRole="button" onPress={() => router.push({ pathname: '/mock/result', params: { uuid: m.uuid } })}
                  style={{ flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 14, borderBottomWidth: 1, borderBottomColor: c.border }}>
                  <View style={{ flex: 1 }}><T variant="h3">{m.exam_name} mock</T><T variant="small" muted>{dateTime(m.taken_at)}</T></View>
                  <T variant="h3">{m.score} / {m.total}</T>
                  <Icon name="chevR" size={16} color={c.muted} />
                </Pressable>
              ))}
            </View>
          ) : null}
        </>
      )}
    </Screen>
  );
}
