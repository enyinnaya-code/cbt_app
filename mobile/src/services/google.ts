/**
 * Google sign-in. It needs native code, so it only works in a development or store build, not in Expo Go.
 * Everything here is optional: when the module or the client id is missing, the app simply hides the button
 * and email sign-in carries on as normal.
 */

interface GoogleModule {
  GoogleSignin: {
    configure: (o: { webClientId: string; iosClientId?: string }) => void;
    hasPlayServices: () => Promise<boolean>;
    signIn: () => Promise<{ type?: string; data?: { idToken?: string | null }; idToken?: string | null }>;
    signOut: () => Promise<unknown>;
  };
}

const webClientId = process.env.EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID;
const iosClientId = process.env.EXPO_PUBLIC_GOOGLE_IOS_CLIENT_ID;

let mod: GoogleModule | null | undefined;

function load(): GoogleModule | null {
  if (mod !== undefined) return mod;
  try {
    // Loaded lazily and guarded: in Expo Go the native part does not exist and requiring it throws.
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    mod = require('@react-native-google-signin/google-signin') as GoogleModule;
    mod.GoogleSignin.configure({ webClientId: webClientId ?? '', iosClientId });
  } catch {
    mod = null;
  }
  return mod;
}

export function googleAvailable(): boolean {
  return !!webClientId && load() !== null;
}

/** Returns Google's ID token, or null when the student backs out. Throws with a plain message on real failures. */
export async function googleIdToken(): Promise<string | null> {
  const g = load();
  if (!g || !webClientId) throw new Error('Google sign-in is not available on this phone yet.');

  try {
    await g.GoogleSignin.hasPlayServices();
    const r = await g.GoogleSignin.signIn();
    if (r.type === 'cancelled') return null;
    return r.data?.idToken ?? r.idToken ?? null;
  } catch (e) {
    const code = (e as { code?: string | number }).code;
    if (code === 'SIGN_IN_CANCELLED' || code === '12501' || code === -5) return null;
    throw new Error('Google sign-in did not work. Please try again, or use your email.');
  }
}

export async function googleSignOut(): Promise<void> {
  try { await load()?.GoogleSignin.signOut(); } catch { /* not signed in with Google */ }
}
