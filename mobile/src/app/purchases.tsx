import { router } from 'expo-router';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { View } from 'react-native';
import { formatBytes, formatDate, naira, plural } from '@/core/format';
import { pendingUpgrades, REMIND_DAYS, type PurchasesData } from '@/core/purchases';
import { buildViews } from '@/core/views';
import { loadPurchases } from '@/services/purchases';
import { useData } from '@/state/data';
import { services } from '@/state/services';
import { Badge, Banner, Button, Card, Empty, IconButton, Row, Screen, T } from '@/ui/components';
import { startDownload } from '@/ui/downloads';
import { unlockOnWebsite } from '@/ui/unlock';
import { useTheme } from '@/ui/theme';

const STATUS_KIND: Record<string, 'green' | 'amber' | 'red' | 'neutral'> = {
  paid: 'green', pending: 'amber', awaiting_confirmation: 'amber', failed: 'red', rejected: 'red', cancelled: 'neutral',
};

/** What the student has unlocked, until when, and what they paid. Works offline from the last list. */
export default function Purchases() {
  const { c } = useTheme();
  const catalog = useData((s) => s.catalog);
  const installed = useData((s) => s.installed);
  const [data, setData] = useState<PurchasesData | null>(null);
  const [stale, setStale] = useState(false);
  const [busy, setBusy] = useState(false);
  const [loaded, setLoaded] = useState(false);

  const upgrades = useMemo(() => pendingUpgrades(buildViews(catalog, installed)), [catalog, installed]);

  const load = useCallback(async () => {
    const { db, api } = services();
    const r = await loadPurchases(db, api);
    setData(r.data);
    setStale(r.stale);
    setLoaded(true);
  }, []);

  useEffect(() => { void load(); }, [load]);

  // Check the server for new purchases, and fetch the full questions for anything just unlocked.
  const refresh = async () => {
    setBusy(true);
    try {
      await useData.getState().refreshCatalogNow();
      await load();
    } finally {
      setBusy(false);
    }
  };

  return (
    <Screen header={<Row style={{ paddingHorizontal: 20, paddingTop: 12 }}><IconButton icon="chevL" label="Back" onPress={() => router.back()} /><T variant="h2">My purchases</T></Row>}>
      {stale && data ? <Banner kind="info" icon="wifioff" text="Showing the list from the last time you were online." /> : null}

      {upgrades.map((s) => (
        <Card key={`${s.examSlug}/${s.slug}`} style={{ gap: 8, backgroundColor: c.accentSoft, borderColor: c.accentSoft }}>
          <T variant="h3">{s.name}: your full questions are ready</T>
          <T variant="small">This phone still has the free sample. Download the full set{s.offered ? ` (${formatBytes(s.offered.size_bytes)})` : ''} to use every question, even offline.</T>
          <Button full={false} icon="download" label="Download the full set" onPress={() => startDownload(s)} />
        </Card>
      ))}

      <View style={{ gap: 4 }}>
        <T variant="h2">Unlocked</T>
        {!loaded ? <T variant="small" muted>Loading…</T> : !data || data.unlocked.length === 0 ? (
          <Empty icon="lock" title="Nothing unlocked yet" body="Every subject still has free questions to try."
            action={<Button label="Unlock subjects" full={false} onPress={() => void unlockOnWebsite()} />} />
        ) : (
          data.unlocked.map((u, i) => {
            const renew = u.expired || u.days_left <= (data.remind_days || REMIND_DAYS);
            return (
              <View key={`${u.exam_slug}/${u.subject_slug}`} style={{ gap: 8, paddingVertical: 12, borderTopWidth: i ? 1 : 0, borderTopColor: c.border }}>
                <Row between style={{ alignItems: 'flex-start' }}>
                  <View style={{ flex: 1, minWidth: 0, gap: 2 }}>
                    <T variant="h3">{u.subject}</T>
                    <T variant="small" muted>{u.exam} · {u.expired ? 'ended' : 'until'} {formatDate(u.expires_at)}</T>
                  </View>
                  {u.expired ? <Badge label="Ended" kind="red" /> : u.days_left <= (data.remind_days || REMIND_DAYS) ? <Badge label={`Ends in ${u.days_left} ${plural(u.days_left, 'day')}`} kind="amber" /> : null}
                </Row>
                {renew ? <Button full={false} small label="Renew" icon="lock" onPress={() => void unlockOnWebsite({ exam: u.exam_slug, subject: u.subject_slug })} /> : null}
              </View>
            );
          })
        )}
      </View>

      <View style={{ gap: 4 }}>
        <T variant="h2">Payments</T>
        {loaded && (!data || data.payments.length === 0) ? <T variant="small" muted>No payments yet.</T> : null}
        {data?.payments.map((p, i) => (
          <View key={p.reference} style={{ gap: 6, paddingVertical: 12, borderTopWidth: i ? 1 : 0, borderTopColor: c.border }}>
            <Row between style={{ alignItems: 'flex-start' }}>
              <View style={{ flex: 1, minWidth: 0, gap: 2 }}>
                <T variant="h3">{p.exam}: {p.subjects.join(', ')}</T>
                <T variant="small" muted>{p.reference} · {formatDate(p.paid_at ?? p.created_at)} · {p.method_label}</T>
              </View>
              <View style={{ alignItems: 'flex-end', gap: 4 }}>
                <T variant="h3">{naira(p.amount)}</T>
                <Badge label={p.status_label} kind={STATUS_KIND[p.status] ?? 'neutral'} />
              </View>
            </Row>
          </View>
        ))}
      </View>

      <Button label={busy ? 'Checking…' : 'Check for new purchases'} variant="outline" icon="refresh" loading={busy} onPress={() => void refresh()} />
      <Button label="Receipts and details on the website" variant="outline" icon="card" onPress={() => void unlockOnWebsite({ page: '/orders' })} />
    </Screen>
  );
}
