import { router, useLocalSearchParams } from 'expo-router';
import { useEffect, useMemo, useState } from 'react';
import { Pressable, ScrollView, View } from 'react-native';
import { formatBytes } from '@/core/format';
import { buildViews } from '@/core/views';
import type { Pack } from '@/core/types';
import { useData, packKey } from '@/state/data';
import { services } from '@/state/services';
import { useSettings } from '@/state/settings';
import { Banner, Button, Card, Chip, Empty, ProgressBar, Row, Screen, Segmented, SubjectTile, T } from '@/ui/components';
import { startDownload } from '@/ui/downloads';
import { Icon } from '@/ui/Icon';
import { useTheme } from '@/ui/theme';

export default function Practice() {
  const { c } = useTheme();
  const params = useLocalSearchParams<{ exam?: string; subject?: string }>();
  const catalog = useData((s) => s.catalog);
  const installed = useData((s) => s.installed);
  const downloads = useData((s) => s.downloads);
  const lang = useSettings((s) => s.targetExam);

  const views = useMemo(() => buildViews(catalog, installed), [catalog, installed]);
  const counts = catalog?.practice_counts ?? [10, 20, 40, 50];

  const [examSlug, setExamSlug] = useState<string | null>(null);
  const [subjectSlug, setSubjectSlug] = useState<string | null>(null);
  const [year, setYear] = useState<number | null>(null);
  const [topicId, setTopicId] = useState<number | null>(null);
  const [mode, setMode] = useState<'instant' | 'end'>('instant');
  const [count, setCount] = useState(20);
  const [pack, setPack] = useState<Pack | null>(null);

  // Arrive from Home's "pick up where you stopped" with the exam and subject already chosen.
  useEffect(() => {
    if (params.exam) setExamSlug(params.exam);
    if (params.subject) setSubjectSlug(params.subject);
  }, [params.exam, params.subject]);

  const exam = views.find((v) => v.slug === examSlug) ?? views.find((v) => v.slug === lang) ?? views.find((v) => v.subjects.some((s) => s.installed)) ?? views[0];
  const subject = exam?.subjects.find((s) => s.slug === subjectSlug && s.installed) ?? null;

  useEffect(() => {
    let live = true;
    setYear(null);
    setTopicId(null);
    if (!exam || !subject) { setPack(null); return; }
    services().packs.load(exam.slug, subject.slug).then((p) => { if (live) setPack(p); });
    return () => { live = false; };
  }, [exam?.slug, subject?.slug, subject?.installed?.version]);

  const start = () => {
    if (!exam || !subject) return;
    router.push({
      pathname: '/practice/session',
      params: { exam: exam.slug, subject: subject.slug, mode, count: String(count), ...(year ? { year: String(year) } : {}), ...(topicId ? { topic: String(topicId) } : {}) },
    });
  };

  return (
    <Screen
      footer={subject ? <Button label={`Start ${count} questions`} onPress={start} /> : undefined}
      header={<View style={{ paddingHorizontal: 20, paddingTop: 12 }}><T variant="h1">Practice</T></View>}
    >
      {views.length === 0 ? (
        <Empty icon="book" title="No questions on this phone yet" body="Connect to the internet once to download your subjects. After that they work offline." />
      ) : (
        <>
          <Segmented value={exam?.slug ?? ''} onChange={(v) => { setExamSlug(v); setSubjectSlug(null); }} options={views.map((v) => ({ value: v.slug, label: v.name }))} />

          <View style={{ gap: 10 }}>
            <T variant="h3">Subject</T>
            <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 12 }}>
              {exam?.subjects.map((s) => {
                const dl = downloads[packKey(s.examSlug, s.slug)];
                const chosen = subjectSlug === s.slug && !!s.installed;
                const canDownload = !s.installed && !!s.offered;
                if (!s.installed && !s.offered) return null;

                return (
                  <Pressable
                    key={s.slug}
                    accessibilityRole="button"
                    accessibilityLabel={`${s.name}. ${s.installed ? `${s.installed.question_count} questions on this phone` : dl ? 'Downloading' : `Download ${formatBytes(s.downloadBytes)}`}`}
                    onPress={() => (s.installed ? setSubjectSlug(s.slug) : canDownload && !dl ? startDownload(s) : undefined)}
                    style={{ width: '47.6%', padding: 14, gap: 12, borderRadius: 18, backgroundColor: c.surface, borderWidth: chosen ? 2 : 1, borderColor: chosen ? c.primary : c.border }}
                  >
                    <Row between>
                      <SubjectTile code={s.code} index={s.index} size={40} />
                      {chosen ? <Icon name="check" size={18} color={c.primary} /> : s.installed ? null : <Icon name="download" size={16} color={c.muted} />}
                    </Row>
                    <View style={{ gap: 4 }}>
                      <T variant="h3">{s.name}</T>
                      {dl && !dl.error ? (
                        <><T variant="small" muted>Downloading {dl.total ? Math.round((dl.done / dl.total) * 100) : 0}%</T><ProgressBar value={dl.total ? (dl.done / dl.total) * 100 : 0} /></>
                      ) : dl?.error ? (
                        <T variant="small" color={c.danger}>{dl.error}</T>
                      ) : s.installed ? (
                        <T variant="small" muted>{s.installed.question_count} questions{s.updateAvailable ? ' · update ready' : ''}</T>
                      ) : (
                        <T variant="small" muted>{formatBytes(s.downloadBytes)}</T>
                      )}
                    </View>
                  </Pressable>
                );
              })}
            </View>
          </View>

          {subject ? (
            <>
              <View style={{ gap: 10 }}>
                <T variant="h3">Year</T>
                <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 8 }}>
                  <Chip label="All years" selected={year === null} onPress={() => setYear(null)} />
                  {subject.installed?.years.map((y) => <Chip key={y} label={String(y)} selected={year === y} onPress={() => setYear(y)} />)}
                </ScrollView>
                <T variant="tiny" muted>One year keeps the questions in the order of the real paper. All years mixes them up.</T>
              </View>

              {pack && pack.topics.length > 0 ? (
                <View style={{ gap: 10 }}>
                  <T variant="h3">Topic <T variant="small" muted>(optional)</T></T>
                  <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 8 }}>
                    <Chip label="Any topic" selected={topicId === null} onPress={() => setTopicId(null)} />
                    {pack.topics.map((t) => <Chip key={t.id} label={t.name} selected={topicId === t.id} onPress={() => setTopicId(t.id)} />)}
                  </ScrollView>
                </View>
              ) : null}

              <View style={{ gap: 10 }}>
                <T variant="h3">How do you want to practise?</T>
                <Segmented value={mode} onChange={setMode} options={[{ value: 'instant', label: 'See answer now' }, { value: 'end', label: 'See answers at end' }]} />
              </View>

              <View style={{ gap: 10 }}>
                <T variant="h3">Number of questions</T>
                <Row wrap style={{ gap: 8 }}>{counts.map((n) => <Chip key={n} label={String(n)} selected={count === n} onPress={() => setCount(n)} />)}</Row>
              </View>
            </>
          ) : (
            <Card kind="flat"><T variant="small" muted>Choose a subject that is on your phone to continue. Subjects marked with a download arrow need to be downloaded once.</T></Card>
          )}

          {installed.some((i) => i.starter) ? (
            <Banner kind="info" icon="download" text="You are using the sample questions that come with the app. Download your subjects to get the full past questions." />
          ) : null}
        </>
      )}
    </Screen>
  );
}
