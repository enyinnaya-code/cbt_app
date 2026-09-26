import { router } from 'expo-router';
import { useState } from 'react';
import { View } from 'react-native';
import { candidates, toSession } from '@/core/selector';
import type { SessionQuestion } from '@/core/types';
import { bookmarks } from '@/db/progress';
import { useFocusLoad } from '@/hooks/useFocusLoad';
import { toggleBookmark } from '@/services/recorder';
import { useData } from '@/state/data';
import { services } from '@/state/services';
import { useSettings } from '@/state/settings';
import { Badge, Button, Card, Empty, IconButton, Row, Screen, T } from '@/ui/components';
import { Explanation, QuestionView } from '@/ui/QuestionView';

interface Item {
  q: SessionQuestion;
  passage: string | null;
  label: string;
}

export default function Saved() {
  const lang = useSettings((s) => s.lang);
  const { data, reload } = useFocusLoad(async () => {
    const { db, packs } = services();
    const marks = await bookmarks(db);
    const items: Item[] = [];
    const missing = new Set<string>();

    const byPack = new Map<string, number[]>();
    for (const m of marks) {
      if (!m.exam_slug || !m.subject_slug) continue;
      const key = `${m.exam_slug}/${m.subject_slug}`;
      byPack.set(key, [...(byPack.get(key) ?? []), m.question_id]);
    }

    for (const [key, ids] of byPack) {
      const [exam, subject] = key.split('/');
      const pack = await packs.load(exam, subject);
      if (!pack) { missing.add(pack ? '' : `${exam.toUpperCase()} ${subject.replace(/-/g, ' ')}`); continue; }
      const s = toSession(pack, candidates(pack, { onlyIds: new Set(ids) }));
      for (const q of s.questions) items.push({ q, passage: q.passageId ? s.passages[q.passageId] : null, label: `${pack.exam.name} ${q.year} · ${pack.subject.display_name || pack.subject.name}` });
    }
    return { items, missing: [...missing], total: marks.length };
  });

  const [open, setOpen] = useState<Record<number, boolean>>({});
  const items = data?.items ?? [];

  const remove = async (q: SessionQuestion) => {
    await toggleBookmark(services().db, useData.getState().index, q, false);
    void useData.getState().refreshPending();
    reload();
  };

  return (
    <Screen header={<Row between style={{ paddingHorizontal: 20, paddingTop: 12 }}><Row><IconButton icon="chevL" label="Back" onPress={() => router.back()} /><T variant="h2">Saved</T></Row></Row>}>
      <T muted>Questions you marked to come back to.</T>

      {items.length > 0 ? (
        <Button label="Practise these" onPress={() => router.push({ pathname: '/practice/session', params: { saved: '1', mode: 'instant', count: String(Math.min(50, Math.max(5, items.length))) } })} />
      ) : null}

      {data?.missing.length ? <Card kind="flat"><T variant="small">Some saved questions are in subjects that are not on this phone: {data.missing.join(', ')}. Download them to see those questions.</T></Card> : null}

      {items.map(({ q, passage, label }) => (
        <Card key={q.id} style={{ gap: 14 }}>
          <Row between><Badge label={label} /><Button label="Remove" variant="outline" small full={false} icon="trash" onPress={() => remove(q)} /></Row>
          <QuestionView q={q} passage={passage} chosen={undefined} showAnswer={!!open[q.id]} onChoose={() => {}} />
          {open[q.id] ? <Explanation q={q} lang={lang} /> : null}
          <Button label={open[q.id] ? 'Hide answer' : 'Show answer'} variant="text" full={false} onPress={() => setOpen((o) => ({ ...o, [q.id]: !o[q.id] }))} />
        </Card>
      ))}

      {data && items.length === 0 && !data.missing.length ? (
        <Empty icon="bookmark" title="Nothing saved yet" body="While you practise, tap the bookmark on a question to keep it here." action={<Button label="Start practising" full={false} onPress={() => router.push('/practice')} />} />
      ) : null}
      <View style={{ height: 8 }} />
    </Screen>
  );
}
