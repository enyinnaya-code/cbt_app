// Each weight is imported from its own path. Importing from the package root would bundle every weight and
// italic (several megabytes) into an app that only uses these six.
import { BricolageGrotesque_700Bold } from '@expo-google-fonts/bricolage-grotesque/700Bold';
import { BricolageGrotesque_800ExtraBold } from '@expo-google-fonts/bricolage-grotesque/800ExtraBold';
import { Figtree_400Regular } from '@expo-google-fonts/figtree/400Regular';
import { Figtree_500Medium } from '@expo-google-fonts/figtree/500Medium';
import { Figtree_600SemiBold } from '@expo-google-fonts/figtree/600SemiBold';
import { Figtree_700Bold } from '@expo-google-fonts/figtree/700Bold';
import { useFonts } from 'expo-font';
import { SplashScreen, Stack } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { View } from 'react-native';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { useBackgroundWork, useBoot } from '@/hooks/useBoot';
import { useSession } from '@/state/session';
import { useSettings } from '@/state/settings';
import { T } from '@/ui/components';
import { ThemeProvider, useTheme } from '@/ui/theme';

SplashScreen.preventAutoHideAsync();

export default function RootLayout() {
  const [fontsLoaded] = useFonts({
    BricolageGrotesque_700Bold, BricolageGrotesque_800ExtraBold, Figtree_400Regular, Figtree_500Medium, Figtree_600SemiBold, Figtree_700Bold,
  });
  const { ready, error } = useBoot();
  const theme = useSettings((s) => s.theme);
  const done = fontsLoaded && ready;

  if (done) SplashScreen.hide();
  if (error) SplashScreen.hide();

  return (
    <SafeAreaProvider>
      <ThemeProvider preference={theme}>
        {error ? <StartupError message={error} /> : done ? <Shell /> : null}
      </ThemeProvider>
    </SafeAreaProvider>
  );
}

function StartupError({ message }: { message: string }) {
  const { c } = useTheme();
  return (
    <View style={{ flex: 1, backgroundColor: c.bg, alignItems: 'center', justifyContent: 'center', padding: 32, gap: 12 }}>
      <T variant="h2">TestaCBT could not start</T>
      <T variant="small" muted style={{ textAlign: 'center' }}>{message}</T>
      <T variant="small" muted style={{ textAlign: 'center' }}>Close the app and open it again. If this keeps happening, restart your phone.</T>
    </View>
  );
}

function Shell() {
  const { isDark } = useTheme();
  const status = useSession((s) => s.status);
  useBackgroundWork(true);

  const signedIn = status === 'in';

  return (
    <>
      <StatusBar style={isDark ? 'light' : 'dark'} />
      <Stack screenOptions={{ headerShown: false, animation: 'fade' }}>
        <Stack.Protected guard={signedIn}>
          <Stack.Screen name="(tabs)" />
          <Stack.Screen name="practice/session" options={{ animation: 'slide_from_bottom', gestureEnabled: false }} />
          <Stack.Screen name="mock/run" options={{ animation: 'slide_from_bottom', gestureEnabled: false }} />
          <Stack.Screen name="mock/result" options={{ gestureEnabled: false }} />
          <Stack.Screen name="saved" options={{ animation: 'slide_from_right' }} />
          <Stack.Screen name="downloads" options={{ animation: 'slide_from_right' }} />
        </Stack.Protected>

        <Stack.Protected guard={!signedIn}>
          <Stack.Screen name="welcome" />
          <Stack.Screen name="sign-in" options={{ animation: 'slide_from_right' }} />
          <Stack.Screen name="sign-up" options={{ animation: 'slide_from_right' }} />
        </Stack.Protected>
      </Stack>
    </>
  );
}
