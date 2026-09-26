import { router } from 'expo-router';
import { useState } from 'react';
import { View } from 'react-native';
import { DEFAULT_FORMATS } from '@/core/views';
import { ApiError } from '@/services/api';
import { completeSignIn } from '@/services/afterAuth';
import { loginWithGoogle, registerWithEmail, validateSignUp } from '@/services/auth';
import { googleAvailable, googleIdToken } from '@/services/google';
import { useData } from '@/state/data';
import { services } from '@/state/services';
import { Banner, Button, Chip, Divider, Field, IconButton, Row, Screen, T } from '@/ui/components';
import { useTheme } from '@/ui/theme';

export default function SignUp() {
  const { c } = useTheme();
  const catalog = useData((s) => s.catalog);
  const exams = catalog?.exams.map((e) => ({ slug: e.slug, name: e.name })) ?? Object.keys(DEFAULT_FORMATS).map((slug) => ({ slug, name: slug.toUpperCase() }));

  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [picked, setPicked] = useState<string[]>([]);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [message, setMessage] = useState<string | null>(null);
  const [busy, setBusy] = useState<'email' | 'google' | null>(null);

  const fail = (e: unknown) => {
    if (e instanceof ApiError && e.kind === 'validation') {
      const mapped: Record<string, string> = {};
      for (const [k, v] of Object.entries(e.fields)) mapped[k] = v[0];
      setErrors(mapped);
    }
    setMessage(e instanceof ApiError || e instanceof Error ? e.message : 'Something went wrong. Please try again.');
  };

  const submit = async () => {
    const found = validateSignUp({ name, email, password });
    setErrors(found);
    setMessage(null);
    if (Object.keys(found).length) return;

    setBusy('email');
    try {
      await completeSignIn(await registerWithEmail(services().api, { name, email, password, exams: picked }));
      router.replace('/');
    } catch (e) {
      fail(e);
    } finally {
      setBusy(null);
    }
  };

  const google = async () => {
    setMessage(null);
    setBusy('google');
    try {
      const token = await googleIdToken();
      if (!token) return;
      await completeSignIn(await loginWithGoogle(services().api, token));
      router.replace('/');
    } catch (e) {
      fail(e);
    } finally {
      setBusy(null);
    }
  };

  const toggle = (slug: string) => setPicked((p) => (p.includes(slug) ? p.filter((x) => x !== slug) : [...p, slug]));

  return (
    <Screen>
      <IconButton icon="chevL" label="Back" onPress={() => router.back()} />
      <View style={{ gap: 6 }}>
        <T variant="h1">Create your account</T>
        <T muted>Takes less than a minute.</T>
      </View>

      {message ? <Banner kind="error" text={message} /> : null}

      {googleAvailable() ? (
        <>
          <Button label="Sign up with Google" variant="outline" loading={busy === 'google'} disabled={busy !== null} onPress={google} />
          <Divider label="or use your email" />
        </>
      ) : null}

      <Field label="Full name" icon="user" value={name} onChangeText={setName} error={errors.name} autoCapitalize="words" autoComplete="name" textContentType="name" returnKeyType="next" />
      <Field label="Email address" icon="mail" value={email} onChangeText={setEmail} error={errors.email} keyboardType="email-address" autoCapitalize="none" autoComplete="email" textContentType="emailAddress" returnKeyType="next" />
      <Field label="Password" icon="lock" value={password} onChangeText={setPassword} error={errors.password} secure autoCapitalize="none" autoComplete="new-password" textContentType="newPassword" />

      <View style={{ gap: 8 }}>
        <T variant="label">Which exams are you writing?</T>
        <Row wrap style={{ gap: 8 }}>
          {exams.map((e) => <Chip key={e.slug} label={e.name} selected={picked.includes(e.slug)} onPress={() => toggle(e.slug)} />)}
        </Row>
      </View>

      <Button label="Create account" loading={busy === 'email'} disabled={busy !== null} onPress={submit} />
      <T variant="tiny" muted style={{ textAlign: 'center' }}>By creating an account you agree to our Terms and Privacy Policy.</T>

      <Row style={{ justifyContent: 'center', gap: 4 }}>
        <T variant="small" muted>Already have an account?</T>
        <T variant="small" color={c.primary} style={{ fontWeight: '700' }} onPress={() => router.replace('/sign-in')} accessibilityRole="link">Sign in</T>
      </Row>
    </Screen>
  );
}
