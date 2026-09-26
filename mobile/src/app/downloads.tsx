import { router } from 'expo-router';
import { useEffect, useMemo, useState } from 'react';
import { Switch, View } from 'react-native';
import { formatBytes } from '@/core/format';
import { buildViews } from '@/core/views';
import { freeDiskBytes } from '@/platform/native';
import { packKey, useData } from '@/state/data';
import { services } from '@/state/services';
import { useSettings } from '@/state/settings';
import { Badge, Button, Card, Empty, IconButton, ProgressBar, Row, Screen, SubjectTile, T } from '@/ui/components';
import { confirmDelete, startDownload } from '@/ui/downloads';
import { Icon } from '@/ui/Icon';
import { useTheme } from '@/ui/theme';

export default function Downloads() {
  const { c } = useTheme();
  const catalog = useData((s) => s.catalog);
  const installed = useData((s) => s.installed);
  const downloads = useData((s) => s.downloads);
  const cancel = useData((s) => s.cancelDownload);
  const remove = useData((s) => s.removePack);
  const online = useData((s) => s.online);
  const { wifiOnly } = useSettings();
  const [used, setUsed] = useState(0);

  const views = useMemo(() => buildViews(catalog, installed), [catalog, installed]);
  const all = views.flatMap((v) => v.subjects);
  const have = all.filter((s) => s.installed);
  const available = all.filter((s) => !s.installed && s.offered);
  const updates = have.filter((s) => s.updateAvailable);
  const free = freeDiskBytes();

  useEffect(() => { services().packs.usedBytes().then(setUsed); }, [installed]);

  return (
    <Screen header={<Row style={{ paddingHorizontal: 20, paddingTop: 12 }}><IconButton icon="chevL" label="Back" onPress={() => router.back()} /><T variant="h2">Downloads</T></Row>}>
      <Card style={{ gap: 10 }}>
        <Row between><T variant="h3">{formatBytes(used)} used</T><T variant="small" muted>{free < Number.MAX_SAFE_INTEGER ? `${formatBytes(free)} free on phone` : ''}</T></Row>
        <ProgressBar value={free < Number.MAX_SAFE_INTEGER ? Math.min(100, (used / (used + free)) * 100) : 0} />
        <T variant="small" muted>Downloaded subjects work with no internet.</T>
      </Card>

      <Card>
        <Row between>
          <View style={{ flex: 1 }}><T variant="h3">Download on Wi-Fi only</T><T variant="small" muted>Saves your mobile data</T></View>
          <Switch value={wifiOnly} onValueChange={(v) => void useSettings.getState().set({ wifiOnly: v })} trackColor={{ true: c.primary, false: c.border }} accessibilityLabel="Download on Wi-Fi only" />
        </Row>
      </Card>

      {!online ? <Card kind="flat"><Row><Icon name="wifioff" size={18} color={c.accentInk} /><T variant="small" style={{ flex: 1 }}>You are offline. Connect to download or update subjects.</T></Row></Card> : null}

      {updates.length > 0 ? (
        <Button label={`Update ${updates.length} ${updates.length === 1 ? 'subject' : 'subjects'}`} variant="outline" icon="refresh" disabled={!online} onPress={() => updates.forEach((s) => startDownload(s))} />
      ) : null}

      <View style={{ gap: 0 }}>
        <T variant="h3" muted style={{ marginBottom: 2 }}>On this phone</T>
        {have.length === 0 ? <Empty icon="download" title="Nothing downloaded yet" body="Download a subject below and it will work offline." /> : null}
        {have.map((s, i) => {
          const dl = downloads[packKey(s.examSlug, s.slug)];
          return (
            <Row key={`${s.examSlug}/${s.slug}`} style={{ paddingVertical: 12, borderBottomWidth: i < have.length - 1 ? 1 : 0, borderBottomColor: c.border }}>
              <SubjectTile code={s.code} index={s.index} />
              <View style={{ flex: 1, gap: 4 }}>
                <T variant="h3">{s.examName} {s.name}</T>
                <T variant="small" muted>
                  {s.installed?.starter ? 'Sample questions' : `${s.installed?.tier === 'free' ? 'Free sample, ' : ''}${s.installed?.years.length ? `${Math.min(...s.installed.years)} to ${Math.max(...s.installed.years)}, ` : ''}${formatBytes(s.installed?.size_bytes ?? 0)}`}
                </T>
                {dl && !dl.error ? <ProgressBar value={dl.total ? (dl.done / dl.total) * 100 : 0} /> : null}
                {dl?.error ? <T variant="small" color={c.danger}>{dl.error}</T> : null}
              </View>
              {dl && !dl.error ? (
                <IconButton icon="x" label="Cancel download" onPress={() => cancel(packKey(s.examSlug, s.slug))} />
              ) : s.updateAvailable ? (
                <Badge label={s.installed?.tier === 'free' && !s.locked ? 'Unlocked' : 'Update'} kind={s.installed?.tier === 'free' && !s.locked ? 'green' : 'amber'} />
              ) : null}
              {!dl || dl.error ? <IconButton icon="trash" label={`Delete ${s.name}`} onPress={() => confirmDelete(s, () => void remove(s.examSlug, s.slug))} /> : null}
            </Row>
          );
        })}
      </View>

      {available.length > 0 ? (
        <View style={{ gap: 0 }}>
          <T variant="h3" muted style={{ marginBottom: 2 }}>Available to download</T>
          {available.map((s, i) => {
            const dl = downloads[packKey(s.examSlug, s.slug)];
            return (
              <Row key={`${s.examSlug}/${s.slug}`} style={{ paddingVertical: 12, borderBottomWidth: i < available.length - 1 ? 1 : 0, borderBottomColor: c.border }}>
                <SubjectTile code={s.code} index={s.index} />
                <View style={{ flex: 1, gap: 4 }}>
                  <T variant="h3">{s.examName} {s.name}</T>
                  {dl && !dl.error ? (
                    <><T variant="small" muted>Downloading, {dl.total ? Math.round((dl.done / dl.total) * 100) : 0}% of {formatBytes(dl.total || s.downloadBytes)}</T><ProgressBar value={dl.total ? (dl.done / dl.total) * 100 : 0} /></>
                  ) : dl?.error ? (
                    <T variant="small" color={c.danger}>{dl.error}</T>
                  ) : (
                    <T variant="small" muted>{s.offered ? `${s.locked ? 'Free sample · ' : ''}${s.offered.question_count} questions · ${formatBytes(s.downloadBytes)}` : ''}</T>
                  )}
                </View>
                {dl && !dl.error ? (
                  <IconButton icon="x" label="Cancel download" onPress={() => cancel(packKey(s.examSlug, s.slug))} />
                ) : (
                  <IconButton icon="download" label={`Download ${s.name}, ${formatBytes(s.downloadBytes)}`} color={c.primary} onPress={() => startDownload(s)} />
                )}
              </Row>
            );
          })}
        </View>
      ) : null}
    </Screen>
  );
}
