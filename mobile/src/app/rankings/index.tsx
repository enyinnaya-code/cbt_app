import { router } from 'expo-router';
import { useEffect, useState } from 'react';
import { View } from 'react-native';
import { board, cachedProfile, type Board, type BoardScope } from '@/services/rankings';
import { ApiError } from '@/services/api';
import { useData } from '@/state/data';
import { services } from '@/state/services';
import { Banner, Button, Card, Empty, IconButton, Row, Screen, ScrollTabs, T } from '@/ui/components';
import { useTheme } from '@/ui/theme';

const TABS: { value: BoardScope; label: string }[] = [{ value: 'week', label: 'This week' }, { value: 'all', label: 'All time' }, { value: 'schools', label: 'Schools' }];

export default function Rankings() {
  const { c } = useTheme();
  const online = useData((s) => s.online);
  const [scope, setScope] = useState<BoardScope>('week');
  const [data, setData] = useState<Board | null>(null);
  const [joined, setJoined] = useState<boolean | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => { cachedProfile(services().db).then((p) => setJoined(p?.opted_in === true)); }, []);

  useEffect(() => {
    let live = true;
    setData(null); setError(null);
    board(services().api, scope).then((b) => { if (live) setData(b); }).catch((e) => {
      if (live) setError(e instanceof ApiError ? e.message : 'Could not load the rankings.');
    });
    return () => { live = false; };
  }, [scope, online]);

  return (
    <Screen header={<Row style={{ paddingHorizontal: 20, paddingTop: 12 }}><IconButton icon="chevL" label="Back" onPress={() => router.back()} /><T variant="h2">Rankings</T></Row>}>
      <T variant="small" muted>From the daily challenge. Students are shown by nickname, never by real name.</T>

      {joined === false ? (
        <Card style={{ gap: 8 }}>
          <T variant="h3">Want to be on the board?</T>
          <T variant="small" muted>Join the daily challenge with a nickname and a picture. It is optional and you can leave whenever you like.</T>
          <Button label="Join the daily challenge" full={false} onPress={() => router.push('/rankings/join' as never)} />
        </Card>
      ) : null}

      <ScrollTabs value={scope} onChange={setScope} options={TABS} />

      {error ? <Banner kind="error" text={online ? error : 'Connect to the internet to see the rankings.'} /> : null}

      {data && scope !== 'schools' && data.me ? (
        <Card kind="primary" style={{ gap: 2 }}>
          <T variant="small" color={c.onPrimary} style={{ opacity: 0.85 }}>{data.period}</T>
          <T variant="h1" color={c.onPrimary}>{data.me.rank ? `#${data.me.rank}` : 'No rank yet'} <T variant="h3" color={c.onPrimary}>· {data.me.points.toLocaleString()} points</T></T>
          {!data.me.played ? <T variant="small" color={c.onPrimary}>Finish today&apos;s challenge to get on the board.</T> : null}
        </Card>
      ) : null}

      {data ? (
        data.rows.length === 0 ? (
          <Empty icon="trophy" title={scope === 'schools' ? 'No school has enough players yet' : 'No one is on the board yet'}
            body={scope === 'schools' ? `A school appears once ${data.min_members ?? 3} students from it have finished a challenge this week.` : 'Finish a daily challenge to be the first.'} />
        ) : (
          <Card style={{ gap: 0, paddingVertical: 4 }}>
            {data.rows.map((r, i) => (
              <Row key={`${r.rank}-${r.nickname ?? r.school}-${i}`} style={{ alignItems: 'flex-start', paddingVertical: 12, borderTopWidth: i ? 1 : 0, borderTopColor: c.border, backgroundColor: r.me || r.mine ? c.primarySoft : 'transparent', paddingHorizontal: 6, borderRadius: 10 }}>
                <View style={{ width: 34, height: 34, borderRadius: 10, alignItems: 'center', justifyContent: 'center', backgroundColor: r.rank <= 3 ? c.accentSoft : c.surface2 }}>
                  <T variant="h3">{r.rank}</T>
                </View>
                {r.emoji ? <T style={{ fontSize: 24, lineHeight: 32 }}>{r.emoji}</T> : null}
                <View style={{ flex: 1, minWidth: 0, gap: 2 }}>
                  <T variant="h3">{scope === 'schools' ? r.school : r.nickname}{r.me ? '  (you)' : ''}</T>
                  {scope === 'schools' ? <T variant="small" muted>{r.members} students played</T> : r.school ? <T variant="small" muted>{r.school}</T> : null}
                </View>
                <T variant="h3">{r.points.toLocaleString()}<T variant="small" muted>{scope === 'schools' ? ' avg' : ' pts'}</T></T>
              </Row>
            ))}
          </Card>
        )
      ) : !error ? <T variant="small" muted>Loading…</T> : null}

      <T variant="tiny" muted>Points: 10 for every right answer plus 100 for finishing.</T>
      {joined ? <Button label="Edit my profile" variant="outline" onPress={() => router.push('/rankings/join' as never)} /> : null}
    </Screen>
  );
}
