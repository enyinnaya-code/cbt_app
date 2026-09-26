import { router } from 'expo-router';
import { useState } from 'react';
import { Alert, Pressable, View } from 'react-native';
import { ago, initials } from '@/core/format';
import { DEFAULT_FORMATS } from '@/core/views';
import { logoutRemote } from '@/services/auth';
import { googleSignOut } from '@/services/google';
import { useData } from '@/state/data';
import { services } from '@/state/services';
import { useSession } from '@/state/session';
import { useSettings } from '@/state/settings';
import { Avatar, Badge, Banner, Button, Card, Chip, Field, Row, Screen, Segmented, T } from '@/ui/components';
import { Icon, type IconName } from '@/ui/Icon';
import { useTheme } from '@/ui/theme';

export default function Profile() {
  const { c } = useTheme();
  const user = useSession((s) => s.user);
  const needsReauth = useSession((s) => s.needsReauth);
  const { theme, lang, targetExam, targetExamDate } = useSettings();
  const catalog = useData((s) => s.catalog);
  const online = useData((s) => s.online);
  const syncing = useData((s) => s.syncing);
  const pending = useData((s) => s.pending);
  const lastSyncedAt = useData((s) => s.lastSyncedAt);
  const [date, setDate] = useState(targetExamDate ?? '');
  const [dateError, setDateError] = useState<string | null>(null);

  const exams = catalog?.exams.map((e) => ({ slug: e.slug, name: e.name })) ?? Object.keys(DEFAULT_FORMATS).map((slug) => ({ slug, name: slug.toUpperCase() }));

  const saveDate = (value: string) => {
    setDate(value);
    if (value === '') { setDateError(null); void useSettings.getState().set({ targetExamDate: null }); return; }
    const ok = /^\d{4}-\d{2}-\d{2}$/.test(value) && !Number.isNaN(new Date(value).getTime());
    setDateError(ok ? null : 'Use the form 2027-01-25');
    if (ok) void useSettings.getState().set({ targetExamDate: value });
  };

  const signOut = async () => {
    // Try to upload first, so nothing the student did is lost when this phone is wiped.
    if (online) await useData.getState().syncNow();
    await useData.getState().refreshPending();
    const waiting = useData.getState().pending;

    const go = async () => {
      await logoutRemote(services().api);
      await googleSignOut();
      await useSession.getState().signOut();
      await useData.getState().refreshPending();
      router.replace('/welcome');
    };

    Alert.alert(
      'Sign out?',
      waiting > 0
        ? `${waiting} of your answers have not been uploaded yet, and signing out removes them from this phone. Connect to the internet and wait for them to upload first.`
        : 'Your progress is backed up. Signing out removes your records from this phone. Your downloaded subjects stay.',
      [{ text: 'Cancel', style: 'cancel' }, { text: waiting > 0 ? 'Sign out anyway' : 'Sign out', style: 'destructive', onPress: () => void go() }],
    );
  };

  const Row2 = ({ icon, title, value, onPress }: { icon: IconName; title: string; value?: string; onPress: () => void }) => (
    <Pressable accessibilityRole="button" onPress={onPress} style={{ flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 14, borderBottomWidth: 1, borderBottomColor: c.border }}>
      <Icon name={icon} size={20} color={c.text} />
      <T variant="h3" style={{ flex: 1 }}>{title}</T>
      {value ? <T variant="small" muted>{value}</T> : null}
      <Icon name="chevR" size={16} color={c.muted} />
    </Pressable>
  );

  return (
    <Screen header={<View style={{ paddingHorizontal: 20, paddingTop: 12 }}><T variant="h1">Profile</T></View>}>
      <Row>
        <Avatar text={initials(user?.name ?? '')} size={60} />
        <View style={{ flex: 1, gap: 4 }}>
          <T variant="h2">{user?.name}</T>
          <T variant="small" muted>{user?.email}</T>
        </View>
      </Row>

      {needsReauth ? <Banner kind="error" icon="lock" text="Your sign-in has expired. Your work is safe on this phone. Sign out and sign in again to keep it backed up." /> : null}

      <Card style={{ gap: 6 }}>
        <Row style={{ alignItems: 'flex-start' }}>
          <Icon name="cloud" size={20} color={pending > 0 ? c.accentInk : c.primary} />
          <View style={{ flex: 1, gap: 2 }}>
            <T variant="h3">{pending > 0 ? `${pending} ${pending === 1 ? 'item' : 'items'} waiting to back up` : 'Progress backed up'}</T>
            <T variant="small" muted>
              {lastSyncedAt ? `Last saved ${ago(lastSyncedAt)}. ` : ''}We save your progress when you are online, so you will not lose it if you change phones.
            </T>
          </View>
        </Row>
        <Button label={syncing ? 'Saving...' : 'Back up now'} variant="outline" small full={false} loading={syncing} disabled={!online || needsReauth} onPress={() => void useData.getState().syncNow()} />
      </Card>

      <View style={{ gap: 10 }}>
        <T variant="h3">Appearance</T>
        <Segmented value={theme} onChange={(v) => void useSettings.getState().set({ theme: v })} options={[{ value: 'system', label: 'System' }, { value: 'light', label: 'Light' }, { value: 'dark', label: 'Dark' }]} />
      </View>

      <View style={{ gap: 10 }}>
        <T variant="h3">Explanation language</T>
        <Segmented value={lang} onChange={(v) => void useSettings.getState().set({ lang: v })} options={[{ value: 'en', label: 'English' }, { value: 'pcm', label: 'Pidgin' }]} />
        <T variant="tiny" muted>Used when a question has both. You can switch on any question.</T>
      </View>

      <View style={{ gap: 10 }}>
        <T variant="h3">My exam</T>
        <Row wrap style={{ gap: 8 }}>
          {exams.map((e) => <Chip key={e.slug} label={e.name} selected={targetExam === e.slug} onPress={() => void useSettings.getState().set({ targetExam: targetExam === e.slug ? null : e.slug })} />)}
        </Row>
        {targetExam ? <Field label="Exam date" icon="target" value={date} onChangeText={saveDate} error={dateError ?? undefined} placeholder="2027-01-25" keyboardType="numbers-and-punctuation" maxLength={10} /> : null}
      </View>

      <View>
        <Row2 icon="bookmark" title="Saved questions" onPress={() => router.push('/saved')} />
        <Row2 icon="download" title="Manage downloads" onPress={() => router.push('/downloads')} />
      </View>

      <Badge label={`TestaCBT ${require('../../../app.json').expo.version}`} kind="neutral" />
      <Button label="Sign out" variant="danger" icon="logout" onPress={signOut} />
    </Screen>
  );
}
