import { router } from 'expo-router';
import { useState } from 'react';
import { View } from 'react-native';
import { ApiError } from '@/services/api';
import { completeSignIn } from '@/services/afterAuth';
import { loginWithEmail, loginWithGoogle, validateSignIn } from '@/services/auth';
import { googleAvailable, googleIdToken } from '@/services/google';
import { services } from '@/state/services';
import { Banner, Button, Card, Divider, Field, IconButton, Row, Screen, T } from '@/ui/components';
import { Icon } from '@/ui/Icon';
import { useTheme } from '@/ui/theme';

export default function SignIn() {
  const { c } = useTheme();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [message, setMessage] = useState<string | null>(null);
  const [busy, setBusy] = useState<'email' | 'google' | null>(null);

  const fail = (e: unknown) => setMessage(e instanceof ApiError || e instanceof Error ? e.message : 'Something went wrong. Please try again.');

  const submit = async () => {
    const found = validateSignIn(email, password);
    setErrors(found);
    setMessage(null);
    if (Object.keys(found).length) return;

    setBusy('email');
    try {
      await completeSignIn(await loginWithEmail(services().api, email, password));
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

  return (
    <Screen>
      <IconButton icon="chevL" label="Back" onPress={() => router.back()} />
      <View style={{ gap: 6 }}>
        <T variant="h1">Welcome back</T>
        <T muted>Sign in to pick up where you stopped.</T>
      </View>

      {message ? <Banner kind="error" text={message} /> : null}

      {googleAvailable() ? (
        <>
          <Button label="Continue with Google" variant="outline" loading={busy === 'google'} disabled={busy !== null} onPress={google} />
          <Divider label="or sign in with email" />
        </>
      ) : null}

      <Field label="Email address" icon="mail" value={email} onChangeText={setEmail} error={errors.email} keyboardType="email-address" autoCapitalize="none" autoComplete="email" textContentType="emailAddress" returnKeyType="next" />
      <Field label="Password" icon="lock" value={password} onChangeText={setPassword} error={errors.password} secure autoCapitalize="none" autoComplete="password" textContentType="password" returnKeyType="go" onSubmitEditing={submit} />

      <Button label="Sign in" loading={busy === 'email'} disabled={busy !== null} onPress={submit} />

      <Card kind="flat">
        <Row style={{ alignItems: 'flex-start' }}>
          <Icon name="wifioff" size={20} color={c.primary} />
          <T variant="small" style={{ flex: 1 }}>You need internet only to sign in. After that, every downloaded subject works offline.</T>
        </Row>
      </Card>

      <Row style={{ justifyContent: 'center', gap: 4 }}>
        <T variant="small" muted>New to TestaCBT?</T>
        <T variant="small" color={c.primary} style={{ fontWeight: '700' }} onPress={() => router.replace('/sign-up')} accessibilityRole="link">Create account</T>
      </Row>
    </Screen>
  );
}
