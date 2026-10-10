import { router } from 'expo-router';
import { useEffect, useState } from 'react';
import { Alert, Linking, Pressable, Switch, View } from 'react-native';
import { ApiError } from '@/services/api';
import { join, leave, policyUrl, refreshProfile, type RankProfile } from '@/services/rankings';
import { useData } from '@/state/data';
import { services } from '@/state/services';
import { Banner, Button, Card, Field, IconButton, Row, Screen, T } from '@/ui/components';
import { useTheme } from '@/ui/theme';

/** Joining the daily challenge: a nickname, a picture, a school if the student likes, and the privacy policy. */
export default function JoinRankings() {
  const { c } = useTheme();
  const online = useData((s) => s.online);
  const [profile, setProfile] = useState<RankProfile | null>(null);
  const [nickname, setNickname] = useState('');
  const [avatar, setAvatar] = useState<string | null>(null);
  const [school, setSchool] = useState('');
  const [accepted, setAccepted] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [fields, setFields] = useState<Record<string, string[]>>({});

  useEffect(() => {
    const { db, api } = services();
    refreshProfile(db, api).then((p) => {
      setProfile(p);
      setNickname(p.nickname ?? '');
      setAvatar(p.avatar);
      setSchool(p.school ?? '');
    }).catch(() => setError(online ? 'Could not load this page. Please try again.' : 'Connect to the internet to join the rankings.'));
  }, [online]);

  const editing = profile?.opted_in === true;

  const submit = async () => {
    if (!avatar) { setFields({ avatar: ['Pick a picture.'] }); return; }
    setBusy(true); setError(null); setFields({});
    try {
      const { db, api } = services();
      await join(db, api, { nickname, avatar, school, acceptPrivacy: accepted });
      router.replace((editing ? '/rankings' : '/(tabs)') as never);
    } catch (e) {
      if (e instanceof ApiError) { setError(e.message); setFields(e.fields); } else setError('Something went wrong. Please try again.');
    } finally {
      setBusy(false);
    }
  };

  const openPolicy = async () => {
    try {
      if (profile) await Linking.openURL(policyUrl(services().api, profile));
    } catch {
      Alert.alert('Could not open the page', 'Please try again in a moment.');
    }
  };

  const leaveNow = () => {
    Alert.alert('Leave the rankings?', 'Your nickname, picture and school are deleted and you disappear from every board. Your practice history stays.', [
      { text: 'Stay', style: 'cancel' },
      { text: 'Leave', style: 'destructive', onPress: async () => {
        try { const { db, api } = services(); await leave(db, api); router.replace('/(tabs)' as never); } catch (e) { Alert.alert('Could not leave', e instanceof ApiError ? e.message : 'Please try again.'); }
      } },
    ]);
  };

  return (
    <Screen header={<Row style={{ paddingHorizontal: 20, paddingTop: 12 }}><IconButton icon="chevL" label="Back" onPress={() => router.back()} /><T variant="h2">{editing ? 'Your ranking profile' : 'Join the daily challenge'}</T></Row>}>
      <T variant="small" muted>
        {editing ? 'This is what other students see on the rankings.' : 'A 10-question challenge every day, ranked against other students. You are shown by a nickname, never your real name.'}
      </T>

      {error ? <Banner kind="error" text={error} /> : null}

      <Field label="Nickname" icon="user" value={nickname} onChangeText={setNickname} error={fields.nickname?.[0]} maxLength={20} autoCapitalize="none" autoCorrect={false} placeholder="For example: SharpOwl22" />
      <T variant="tiny" muted style={{ marginTop: -10 }}>3 to 20 letters or numbers. Everyone on the rankings can see it, so do not use your full name.</T>

      <View style={{ gap: 10 }}>
        <T variant="h3">Pick a picture</T>
        <Row wrap style={{ gap: 10 }}>
          {(profile?.avatars ?? []).map((a) => {
            const on = avatar === a.key;
            return (
              <Pressable key={a.key} accessibilityRole="radio" accessibilityState={{ selected: on }} accessibilityLabel={a.key} onPress={() => setAvatar(a.key)}
                style={{ width: 54, height: 54, borderRadius: 16, alignItems: 'center', justifyContent: 'center', backgroundColor: on ? c.primarySoft : c.surface, borderWidth: on ? 2 : 1, borderColor: on ? c.primary : c.border }}>
                <T style={{ fontSize: 26, lineHeight: 32 }}>{a.emoji}</T>
              </Pressable>
            );
          })}
        </Row>
        {fields.avatar?.[0] ? <T variant="small" color={c.danger}>{fields.avatar[0]}</T> : null}
      </View>

      <Field label="School (optional)" icon="book" value={school} onChangeText={setSchool} error={fields.school?.[0]} maxLength={80} placeholder="Add it to help your school climb the board" />
      <T variant="tiny" muted style={{ marginTop: -10 }}>If you add it, other students can see which school you named, and your points count toward it. Leave it empty to stay on your own.</T>

      <Card style={{ gap: 10 }}>
        <Row style={{ alignItems: 'flex-start' }}>
          <Switch value={accepted} onValueChange={setAccepted} accessibilityLabel="I accept the privacy policy" trackColor={{ true: c.primary, false: c.border }} />
          <View style={{ flex: 1, gap: 4 }}>
            <T variant="small">I have read and accept the privacy policy. I understand my nickname, picture, points and school (if I add one) are shown to other students on the rankings, and that I can leave at any time.</T>
            <T variant="small" color={c.primary} style={{ fontWeight: '700' }} onPress={() => void openPolicy()} accessibilityRole="link">Read the privacy policy</T>
          </View>
        </Row>
        {fields.accept_privacy?.[0] ? <T variant="small" color={c.danger}>{fields.accept_privacy[0]}</T> : null}
      </Card>

      <Button label={editing ? 'Save changes' : 'Join and start'} loading={busy} disabled={!profile || !online} onPress={() => void submit()} />
      {!online ? <T variant="small" muted style={{ textAlign: 'center' }}>Connect to the internet to join or change your profile.</T> : null}

      {editing ? <Button label="Leave the rankings" variant="outline" onPress={leaveNow} /> : null}
    </Screen>
  );
}
