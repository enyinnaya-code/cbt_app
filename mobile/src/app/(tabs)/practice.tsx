import { router, useLocalSearchParams } from 'expo-router';
import { useEffect, useMemo, useState } from 'react';
import { Pressable, View } from 'react-native';
import { formatBytes, formatDate, naira } from '@/core/format';
import { candidates, progressOf } from '@/core/selector';
import { buildViews } from '@/core/views';
import { answeredIds } from '@/db/progress';
import type { Pack } from '@/core/types';
import { useData, packKey } from '@/state/data';
import { services } from '@/state/services';
import { useSettings } from '@/state/settings';
import { Banner, Button, Card, Chip, Empty, ProgressBar, Row, Screen, ScrollTabs, Segmented, Sheet, SubjectTile, T } from '@/ui/components';
import { startDownload } from '@/ui/downloads';
import { Icon } from '@/ui/Icon';
import { unlockOnWebsite } from '@/ui/unlock';
import { useTheme } from '@/ui/theme';

export default function Practice() {
  const { c } = useTheme();
  const params = useLocalSearchParams<{ exam?: string; subject?: string }>();
  const catalog = useData((s) => s.catalog);
  const installed = useData((s) => s.installed);
  const downloads = useData((s) => s.downloads);
  const target = useSettings((s) => s.targetExam);

  const views = useMemo(() => buildViews(catalog, installed), [catalog, installed]);
  const counts = catalog?.practice_counts ?? [10, 20, 40, 50];

  const [examSlug, setExamSlug] = useState<string | null>(null);
  const [subjectSlug, setSubjectSlug] = useState<string | null>(null);
  const [open, setOpen] = useState(false);
  const [year, setYear] = useState<number | null>(null);
  const [topicId, setTopicId] = useState<number | null>(null);
  const [mode, setMode] = useState<'instant' | 'end'>('instant');
  const [count, setCount] = useState(20);
  const [pack, setPack] = useState<Pack | null>(null);
  const [seen, setSeen] = useState<Set<number>>(new Set());

  // Arrive from Home's "pick up where you stopped" with the exam and subject already chosen, and its options open.
  useEffect(() => {
    if (params.exam) setExamSlug(params.exam);
    if (params.subject) { setSubjectSlug(params.subject); setOpen(true); }
  }, [params.exam, params.subject]);

  const exam = views.find((v) => v.slug === examSlug) ?? views.find((v) => v.slug === target) ?? views.find((v) => v.subjects.some((s) => s.installed)) ?? views[0];
  const subject = exam?.subjects.find((s) => s.slug === subjectSlug && s.installed) ?? null;

  useEffect(() => {
    let live = true;
    setYear(null);
    setTopicId(null);
    if (!exam || !subject) { setPack(null); return; }
    services().packs.load(exam.slug, subject.slug).then((p) => { if (live) setPack(p); });
    answeredIds(services().db).then((ids) => { if (live) setSeen(ids); }).catch(() => {});
    return () => { live = false; };
  }, [exam?.slug, subject?.slug, subject?.installed?.version]);

  const start = () => {
    if (!exam || !subject) return;
    setOpen(false);
    router.push({
      pathname: '/practice/session',
      params: { exam: exam.slug, subject: subject.slug, mode, count: String(count), ...(year ? { year: String(year) } : {}), ...(topicId ? { topic: String(topicId) } : {}) },
    });
  };

  // "You have answered 120 of 350": new questions come first, and the student is told when they have done them all.
  const done = useMemo(() => (pack ? progressOf(candidates(pack, { year, topicId }), seen) : null), [pack, year, topicId, seen]);

  const choose = (slug: string) => { setSubjectSlug(slug); setOpen(true); };

  return (
    <Screen header={<View style={{ paddingHorizontal: 20, paddingTop: 12 }}><T variant="h1">Practice</T></View>}>
      {views.length === 0 ? (
        <Empty icon="book" title="No questions on this phone yet" body="Connect to the internet once to download your subjects. After that they work offline." />
      ) : (
        <>
          <ScrollTabs value={exam?.slug ?? ''} onChange={(v) => { setExamSlug(v); setSubjectSlug(null); setOpen(false); }} options={views.map((v) => ({ value: v.slug, label: v.name }))} />

          <View style={{ gap: 10 }}>
            <T variant="h3">Choose a subject</T>
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
                    onPress={() => (s.installed ? choose(s.slug) : canDownload && !dl ? startDownload(s) : undefined)}
                    style={{ width: '47.6%', padding: 14, gap: 12, borderRadius: 18, backgroundColor: c.surface, borderWidth: chosen ? 2 : 1, borderColor: chosen ? c.primary : c.border }}
                  >
                    <Row between>
                      <SubjectTile code={s.code} index={s.index} size={40} />
                      {chosen ? <Icon name="check" size={18} color={c.primary} /> : s.installed ? (s.locked ? <Icon name="lock" size={16} color={c.muted} /> : null) : <Icon name="download" size={16} color={c.muted} />}
                    </Row>
                    <View style={{ gap: 4 }}>
                      <T variant="h3">{s.name}</T>
                      {dl && !dl.error ? (
                        <><T variant="small" muted>Downloading {dl.total ? Math.round((dl.done / dl.total) * 100) : 0}%</T><ProgressBar value={dl.total ? (dl.done / dl.total) * 100 : 0} /></>
                      ) : dl?.error ? (
                        <T variant="small" color={c.danger}>{dl.error}</T>
                      ) : s.installed ? (
                        <T variant="small" muted>{s.locked ? 'Free sample · ' : ''}{s.installed.question_count} questions{s.updateAvailable && !s.locked ? ' · update ready' : ''}</T>
                      ) : (
                        <T variant="small" muted>{formatBytes(s.downloadBytes)}</T>
                      )}
                    </View>
                  </Pressable>
                );
              })}
            </View>
          </View>

          {installed.some((i) => i.starter) ? (
            <Banner kind="info" icon="download" text="You are using the sample questions that come with the app. Your subjects will download automatically when you are online." />
          ) : null}
        </>
      )}

      <Sheet
        visible={open && !!subject}
        title={subject?.name ?? ''}
        subtitle={exam ? `${exam.name} practice` : undefined}
        onClose={() => setOpen(false)}
        footer={<Button label={`Start ${count} questions`} onPress={start} />}
      >
        {subject && !subject.locked && subject.updateAvailable && subject.installed?.tier === 'free' ? (
          <Card style={{ gap: 8, backgroundColor: c.accentSoft, borderColor: c.accentSoft }}>
            <T variant="h3">Your full questions are ready</T>
            <T variant="small">You unlocked {subject.name}, but this phone still has the free sample ({subject.installed.question_count} questions).</T>
            <Button full={false} icon="download" label={`Download the full set${subject.offered ? ` (${formatBytes(subject.offered.size_bytes)})` : ''}`} onPress={() => startDownload(subject)} />
          </Card>
        ) : null}

        {subject?.locked ? (
          <Card style={{ gap: 10, backgroundColor: c.accentSoft, borderColor: c.accentSoft }}>
            <T variant="h3">You are using the free sample of {subject.name}</T>
            <T variant="small">
              {subject.installed?.question_count ?? subject.freeQuestions} questions{subject.fullQuestions ? ` of ${subject.fullQuestions}` : ''}. Unlock all of them{subject.price > 0 ? ` for ${naira(subject.price)}` : ''}, then they work offline too.
            </T>
            <Button label={subject.price > 0 ? `Unlock for ${naira(subject.price)}` : `Unlock ${subject.name}`} icon="lock" onPress={() => void unlockOnWebsite({ exam: exam?.slug, subject: subject.slug })} />
            <Button label="I have paid: refresh" variant="outline" small full={false} onPress={() => void useData.getState().refreshCatalogNow()} />
          </Card>
        ) : subject?.expiresAt ? (
          <T variant="small" muted>Unlocked until {formatDate(subject.expiresAt)}.</T>
        ) : null}

        <View style={{ gap: 10 }}>
          <T variant="h3">Year</T>
          <Row wrap style={{ gap: 8 }}>
            <Chip label="All years" selected={year === null} onPress={() => setYear(null)} />
            {subject?.installed?.years.map((y) => <Chip key={y} label={String(y)} selected={year === y} onPress={() => setYear(y)} />)}
          </Row>
          <T variant="tiny" muted>One year keeps the questions in the order of the real paper. All years mixes them up.</T>
        </View>

        {pack && pack.topics.length > 0 ? (
          <View style={{ gap: 10 }}>
            <T variant="h3">Topic <T variant="small" muted>(optional)</T></T>
            <Row wrap style={{ gap: 8 }}>
              <Chip label="Any topic" selected={topicId === null} onPress={() => setTopicId(null)} />
              {pack.topics.map((t) => <Chip key={t.id} label={t.name} selected={topicId === t.id} onPress={() => setTopicId(t.id)} />)}
            </Row>
          </View>
        ) : null}

        <View style={{ gap: 10 }}>
          <T variant="h3">How do you want to practise?</T>
          <Segmented value={mode} onChange={setMode} options={[{ value: 'instant', label: 'See answer now' }, { value: 'end', label: 'See answers at end' }]} />
        </View>

        <View style={{ gap: 10 }}>
          <T variant="h3">Number of questions</T>
          <Row wrap style={{ gap: 8 }}>{counts.map((n) => <Chip key={n} label={String(n)} selected={count === n} onPress={() => setCount(n)} />)}</Row>
          {done && done.total > 0 ? (
            <T variant="tiny" muted>{done.fresh === 0 ? `You have answered all ${done.total} questions here, so some will repeat. That is good revision.` : done.answered === 0 ? 'Every question here is new to you.' : `You have answered ${done.answered} of ${done.total}. Questions you have not seen come first.`}</T>
          ) : null}
        </View>
      </Sheet>
    </Screen>
  );
}
